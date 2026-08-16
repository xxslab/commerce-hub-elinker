<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Licensing\BillingAuditLogger;
use App\Services\Licensing\LicenseHubClient;
use App\Services\Licensing\LicenseHubUnavailableException;
use App\Services\Licensing\ProductLinkRejectedException;
use App\Services\Licensing\SubscriptionEntitlementService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * "Ustawienia -> Plan i billing". Deliberately outside subscription.active
 * gating (see EnsureActiveSubscription's docblock) — a suspended company
 * must still be able to see its own billing state.
 *
 * A raw license_hub_workspace_id is NEVER accepted from the browser as
 * proof of ownership: knowing or guessing another customer's workspace_id
 * would otherwise let a company admin attach their own company to someone
 * else's subscription. The only supported way to link is via a one-time
 * connection code (License Hub's product-link token, product=elinker),
 * redeemed through LicenseHubClient::consumeProductLink() — see that
 * method's docblock and License Hub's ProductLinkTokenService.
 */
class BillingSettingsController extends Controller
{
    public function show(SubscriptionEntitlementService $entitlements)
    {
        $company = $this->company();

        return view('settings.billing', [
            'company' => $company,
            'isActive' => $entitlements->isActive($company),
            'gatingApplicable' => $entitlements->isGatingApplicable($company),
            'auditLog' => $company->billingAuditLogs()->latest('id')->limit(20)->get(),
        ]);
    }

    public function connect(
        Request $request,
        LicenseHubClient $client,
        SubscriptionEntitlementService $entitlements,
        BillingAuditLogger $audit,
    ) {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:128'],
        ]);

        $company = $this->company();

        try {
            $workspaceId = $client->consumeProductLink($data['token']);
        } catch (ProductLinkRejectedException $e) {
            $audit->log('billing.connect_rejected', $company->id, null, $request->ip());

            return back()->withErrors(['token' => $e->getMessage()]);
        } catch (LicenseHubUnavailableException $e) {
            report($e);
            $audit->log('billing.connect_unavailable', $company->id, null, $request->ip());

            return back()->withErrors(['token' => 'License Hub jest chwilowo niedostępny. Spróbuj ponownie za chwilę.']);
        }

        // Defense in depth on top of License Hub's own single-use token and
        // this table's DB-level unique constraint on license_hub_workspace_id:
        // a token could in principle be issued twice for the same workspace
        // (e.g. operator error), so re-check here before writing.
        $alreadyClaimedByAnother = Company::query()
            ->where('license_hub_workspace_id', $workspaceId)
            ->where('id', '!=', $company->id)
            ->exists();

        if ($alreadyClaimedByAnother) {
            $audit->log('billing.connect_conflict', $company->id, $workspaceId, $request->ip());

            return back()->withErrors(['token' => 'Ten workspace jest już połączony z innym kontem.']);
        }

        try {
            $company->forceFill(['license_hub_workspace_id' => $workspaceId])->save();
        } catch (QueryException $e) {
            $audit->log('billing.connect_conflict', $company->id, $workspaceId, $request->ip());

            return back()->withErrors(['token' => 'Ten workspace jest już połączony z innym kontem.']);
        }

        $audit->log('billing.connected', $company->id, $workspaceId, $request->ip(), [
            'user_id' => $request->user()?->id,
        ]);

        $entitlements->refresh($company->fresh());

        return back()->with('ok', 'Konto połączone z License Hub. Stan konta odświeżony.');
    }

    /**
     * Deliberate, explicit disconnect — never a side effect of anything
     * else. Only clears the company's billing-identity columns; orders,
     * sales channels, and shipments are untouched (this method never
     * queries those tables), so a disconnect can never look like data loss.
     */
    public function disconnect(Request $request, BillingAuditLogger $audit)
    {
        $company = $this->company();
        $previousWorkspaceId = $company->license_hub_workspace_id;

        if ($previousWorkspaceId === null) {
            return back()->with('error', 'Konto nie jest połączone z License Hub.');
        }

        $company->forceFill([
            'license_hub_workspace_id' => null,
            'entitlement_status' => null,
            'entitlement_plan_code' => null,
            'entitlement_features' => null,
            'entitlement_checked_at' => null,
            'entitlement_sync_status' => null,
        ])->save();

        $audit->log('billing.disconnected', $company->id, $previousWorkspaceId, $request->ip(), [
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('ok', 'Konto odłączone od License Hub. Dane zamówień, kanałów i przesyłek pozostają nienaruszone.');
    }

    public function refresh(SubscriptionEntitlementService $entitlements)
    {
        $company = $this->company();

        if (!$company->license_hub_workspace_id) {
            return back()->with('error', 'Najpierw połącz konto z License Hub.');
        }

        $entitlements->refresh($company);

        return back()->with('ok', 'Stan konta odświeżony.');
    }
}

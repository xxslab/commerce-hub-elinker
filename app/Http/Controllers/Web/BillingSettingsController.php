<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Licensing\SubscriptionEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Ustawienia -> Plan i billing". Deliberately outside subscription.active
 * gating (see EnsureActiveSubscription's docblock) — a suspended company
 * must still be able to see its own billing state.
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
        ]);
    }

    public function link(Request $request, SubscriptionEntitlementService $entitlements)
    {
        $data = $request->validate([
            'license_hub_workspace_id' => ['required', 'string', 'max:64', Rule::unique('companies', 'license_hub_workspace_id')],
        ]);

        $company = $this->company();
        $company->forceFill(['license_hub_workspace_id' => $data['license_hub_workspace_id']])->save();

        $entitlements->refresh($company->fresh());

        return back()->with('ok', 'Workspace License Hub powiązany. Stan konta odświeżony.');
    }

    public function refresh(SubscriptionEntitlementService $entitlements)
    {
        $company = $this->company();

        if (!$company->license_hub_workspace_id) {
            return back()->with('error', 'Najpierw powiąż workspace License Hub.');
        }

        $entitlements->refresh($company);

        return back()->with('ok', 'Stan konta odświeżony.');
    }
}

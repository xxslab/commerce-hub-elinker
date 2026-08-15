<?php

namespace App\Services\Licensing;

use App\Models\Company;

/**
 * Reads/refreshes a Company's local entitlement projection. Gating
 * decisions (isActive/isGatingApplicable) always read the LOCAL columns on
 * Company, never call License Hub synchronously — refresh() is the only
 * method that makes an HTTP call, and it is meant to run from a scheduled
 * job (RefreshEntitlementJob / commerce-hub:sync-entitlement), not per
 * request.
 *
 * The core guarantee (mirrors License Hub's own BillingAccount doc — a
 * License Hub/WHMCS outage must never itself suspend an active company):
 * refresh() only ever writes entitlement_status/entitlement_plan_code on a
 * SUCCESSFUL check. On failure it writes entitlement_sync_status = degraded
 * and leaves status/plan_code exactly as they were.
 */
class SubscriptionEntitlementService
{
    public function __construct(private LicenseHubClient $client)
    {
    }

    public function refresh(Company $company): void
    {
        if (!$company->license_hub_workspace_id) {
            return;
        }

        try {
            $data = $this->client->checkEntitlement($company->license_hub_workspace_id);
        } catch (LicenseHubUnavailableException $e) {
            report($e);
            $company->forceFill(['entitlement_sync_status' => 'degraded'])->save();

            return;
        }

        $company->forceFill([
            'entitlement_status' => $data['status'] ?? ($data['active'] ? 'active' : 'unknown'),
            'entitlement_plan_code' => $data['plan_code'] ?? null,
            'entitlement_features' => $data['features'] ?? [],
            'entitlement_checked_at' => now(),
            'entitlement_sync_status' => 'ok',
        ])->save();
    }

    /**
     * Whether gating should even be considered for this company. False for
     * any company not linked to a License Hub workspace, or while the
     * master switch is off — see config('commerce-hub.license_hub.enforce_gating')'s
     * docblock in config/commerce-hub.php for why this defaults off.
     */
    public function isGatingApplicable(Company $company): bool
    {
        return $company->license_hub_workspace_id !== null
            && (bool) config('commerce-hub.license_hub.enforce_gating', false);
    }

    /**
     * True unless the company is linked, gating is enabled, AND the last
     * known status is explicitly not active. A company that has never been
     * checked yet (entitlement_status is null) is treated as active rather
     * than blocked — an unset local cache must never read as "suspended".
     */
    public function isActive(Company $company): bool
    {
        if (!$this->isGatingApplicable($company)) {
            return true;
        }

        if ($company->entitlement_status === null) {
            return true;
        }

        return $company->entitlement_status === 'active';
    }

    public function feature(Company $company, string $key): ?array
    {
        $features = $company->entitlement_features ?? [];

        return $features[$key] ?? null;
    }
}

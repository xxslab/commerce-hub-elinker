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

    /**
     * Whether $key is currently allowed for $company. Always true while
     * gating isn't applicable (see isGatingApplicable) — the master switch
     * and per-company linkage guard every one of these methods exactly as
     * they guard isActive(), so a feature key never becomes a second,
     * inconsistent gating path.
     *
     * Default-deny: a feature key with no matching plan_features row
     * (unmapped) resolves to false, same as License Hub's own
     * EntitlementService::can() — an admin must explicitly grant a
     * feature, nothing is implicitly allowed.
     *
     * For a "limit" type feature, "allowed" means there is still room
     * under the limit (usage < limit) or the feature is unlimited — it
     * does NOT mean the feature is merely present. Use limit()/usage() to
     * inspect the raw numbers.
     */
    public function can(Company $company, string $key): bool
    {
        if (!$this->isGatingApplicable($company)) {
            return true;
        }

        $feature = $this->feature($company, $key);
        if ($feature === null) {
            return false;
        }

        if (($feature['type'] ?? null) === 'boolean') {
            return (bool) ($feature['enabled'] ?? false);
        }

        if ($feature['unlimited'] ?? false) {
            return true;
        }

        return (int) ($feature['usage'] ?? 0) < (int) ($feature['limit'] ?? 0);
    }

    /**
     * The numeric limit for a "limit"-type feature key, or null when
     * unlimited or when gating isn't applicable (no ceiling to report).
     * Returns 0 for an unmapped/boolean-type key — never null, so callers
     * can't mistake "not configured" for "unlimited".
     */
    public function limit(Company $company, string $key): ?int
    {
        if (!$this->isGatingApplicable($company)) {
            return null;
        }

        $feature = $this->feature($company, $key);
        if ($feature === null || ($feature['type'] ?? null) !== 'limit') {
            return 0;
        }

        return ($feature['unlimited'] ?? false) ? null : (int) ($feature['limit'] ?? 0);
    }

    public function usage(Company $company, string $key): int
    {
        $feature = $this->feature($company, $key);

        return (int) ($feature['usage'] ?? 0);
    }

    /**
     * Throws when $key is not allowed for $company. Intended for the one
     * or two genuinely gated write actions (new sales channel, new sync,
     * new InPost shipment) rather than being sprinkled through view logic
     * — see EnsureActiveSubscription, which is the actual enforcement
     * point and calls this.
     *
     * @throws FeatureNotAllowedException
     */
    public function assertAllowed(Company $company, string $key): void
    {
        if (!$this->can($company, $key)) {
            throw new FeatureNotAllowedException("Feature not allowed for company {$company->id}: {$key}");
        }
    }
}

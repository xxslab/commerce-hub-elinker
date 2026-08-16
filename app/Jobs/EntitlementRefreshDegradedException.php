<?php

namespace App\Jobs;

/**
 * Internal, job-only signal thrown by RefreshEntitlementJob when
 * SubscriptionEntitlementService::refresh() completed but left the company
 * in entitlement_sync_status=degraded. Never thrown by, or caught in,
 * application code outside this job -- see RefreshEntitlementJob::handle()
 * for why this exists instead of refresh() itself throwing.
 */
class EntitlementRefreshDegradedException extends \RuntimeException
{
    public function __construct(int $companyId)
    {
        parent::__construct("Entitlement refresh left company {$companyId} in a degraded sync state.");
    }
}

<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\Licensing\SubscriptionEntitlementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * Queued by commerce-hub:sync-entitlement (one dispatch per linked company,
 * per scheduler tick — see RefreshEntitlementsCommand). WithoutOverlapping
 * is keyed per company so a slow/backed-up queue can never let two
 * refreshes for the SAME company run concurrently or pile up as duplicate
 * queued jobs ("job storm") if a tick fires again before the previous
 * job finished; it does not limit how many DIFFERENT companies' jobs run
 * in parallel.
 */
class RefreshEntitlementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900];

    public function __construct(public int $companyId)
    {
    }

    public function middleware(): array
    {
        // expireAfter comfortably covers tries+backoff (60+300+900=1260s)
        // with margin, so a genuinely stuck lock can't outlive a single
        // scheduler interval and silently block this company's refreshes.
        return [(new WithoutOverlapping('entitlement-refresh:'.$this->companyId))->expireAfter(1500)];
    }

    public function handle(SubscriptionEntitlementService $service): void
    {
        $company = Company::find($this->companyId);
        if (!$company) {
            return;
        }

        // SubscriptionEntitlementService::refresh() deliberately never
        // throws on a License Hub outage -- it swallows
        // LicenseHubUnavailableException and records
        // entitlement_sync_status=degraded instead, because it is also
        // called synchronously from BillingSettingsController (a user
        // clicking "Odśwież stan konta" must see a graceful degraded
        // state, never a 500). That guarantee must not change here.
        //
        // Instead, this job inspects the resulting sync_status itself and
        // throws only from here, so this job's own $tries/$backoff can
        // retry a transient blip sooner than the next full scheduler
        // interval, without weakening refresh()'s contract for its other
        // caller.
        $service->refresh($company);

        if ($company->fresh()?->entitlement_sync_status === 'degraded') {
            throw new EntitlementRefreshDegradedException($this->companyId);
        }
    }

    /**
     * Reached only after all retries are exhausted and the company is
     * still degraded. Deliberately does NOT touch entitlement_status or
     * entitlement_plan_code -- refresh() already recorded "degraded" on
     * the first failed attempt, which is the correct final state for a
     * License Hub outage; this is failure *visibility* only (so the
     * outage shows up wherever queue failures are monitored), not a
     * second, different failure mode to react to.
     */
    public function failed(\Throwable $exception): void
    {
        report($exception);
    }
}

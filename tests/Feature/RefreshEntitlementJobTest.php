<?php

namespace Tests\Feature;

use App\Jobs\EntitlementRefreshDegradedException;
use App\Jobs\RefreshEntitlementJob;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RefreshEntitlementJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'commerce-hub.license_hub.url' => 'https://license.example.test',
            'commerce-hub.license_hub.key_id' => 'elinker-key',
            'commerce-hub.license_hub.secret' => 'elinker-secret',
        ]);
    }

    private function makeCompany(string $email, string $workspaceId): Company
    {
        $company = Company::create(['name' => 'Acme', 'email' => $email]);
        $company->forceFill(['license_hub_workspace_id' => $workspaceId])->save();

        return $company->fresh();
    }

    public function test_job_updates_the_company_on_a_successful_check(): void
    {
        $company = $this->makeCompany('a@example.test', '5001');
        Http::fake(['license.example.test/*' => Http::response([
            'active' => true, 'status' => 'active', 'plan_code' => 'pro', 'features' => [],
        ], 200)]);

        (new RefreshEntitlementJob($company->id))->handle(app(\App\Services\Licensing\SubscriptionEntitlementService::class));

        $fresh = $company->fresh();
        self::assertSame('active', $fresh->entitlement_status);
        self::assertSame('ok', $fresh->entitlement_sync_status);
    }

    /**
     * refresh() itself never throws (that guarantee is unchanged -- see
     * its own docblock), but the job must still surface a degraded result
     * as a job failure so $tries/$backoff can retry sooner than the next
     * full scheduler interval.
     */
    public function test_job_throws_when_the_refresh_leaves_the_company_degraded(): void
    {
        $company = $this->makeCompany('b@example.test', '5002');
        Http::fake(['license.example.test/*' => Http::response(['error' => 'internal'], 500)]);

        $this->expectException(EntitlementRefreshDegradedException::class);
        (new RefreshEntitlementJob($company->id))->handle(app(\App\Services\Licensing\SubscriptionEntitlementService::class));
    }

    public function test_job_does_not_throw_or_error_for_a_deleted_company(): void
    {
        (new RefreshEntitlementJob(999999))->handle(app(\App\Services\Licensing\SubscriptionEntitlementService::class));
        $this->addToAssertionCount(1);
    }

    public function test_job_is_keyed_without_overlapping_per_company(): void
    {
        $job = new RefreshEntitlementJob(42);
        $middleware = $job->middleware();

        self::assertCount(1, $middleware);
        self::assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    /**
     * Two RefreshEntitlementJob instances for two DIFFERENT companies must
     * use different overlapping-lock keys -- otherwise one company's
     * refresh would block another's, not just prevent duplicates of the
     * SAME company's job.
     */
    public function test_without_overlapping_key_is_scoped_per_company(): void
    {
        $jobA = new RefreshEntitlementJob(1);
        $jobB = new RefreshEntitlementJob(2);

        $keyA = (fn () => $this->key)->call($jobA->middleware()[0]);
        $keyB = (fn () => $this->key)->call($jobB->middleware()[0]);

        self::assertNotSame($keyA, $keyB);
    }

    public function test_failed_reports_the_exception_without_throwing(): void
    {
        $job = new RefreshEntitlementJob(1);
        $job->failed(new EntitlementRefreshDegradedException(1));
        $this->addToAssertionCount(1);
    }
}

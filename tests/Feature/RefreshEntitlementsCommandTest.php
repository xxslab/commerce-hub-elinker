<?php

namespace Tests\Feature;

use App\Jobs\RefreshEntitlementJob;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RefreshEntitlementsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_a_refresh_job_only_for_linked_companies(): void
    {
        Bus::fake();

        $linked = Company::create(['name' => 'Linked', 'email' => 'linked@example.test']);
        $linked->forceFill(['license_hub_workspace_id' => '6001'])->save();
        $unlinked = Company::create(['name' => 'Unlinked', 'email' => 'unlinked@example.test']);

        $this->artisan('commerce-hub:sync-entitlement')->assertSuccessful();

        Bus::assertDispatched(RefreshEntitlementJob::class, fn ($job) => $job->companyId === $linked->id);
        Bus::assertNotDispatched(RefreshEntitlementJob::class, fn ($job) => $job->companyId === $unlinked->id);
    }

    public function test_it_dispatches_one_job_per_linked_company_not_a_single_bulk_job(): void
    {
        Bus::fake();

        $a = Company::create(['name' => 'A', 'email' => 'a2@example.test']);
        $a->forceFill(['license_hub_workspace_id' => '6002'])->save();
        $b = Company::create(['name' => 'B', 'email' => 'b2@example.test']);
        $b->forceFill(['license_hub_workspace_id' => '6003'])->save();

        $this->artisan('commerce-hub:sync-entitlement')->assertSuccessful();

        Bus::assertDispatchedTimes(RefreshEntitlementJob::class, 2);
    }
}

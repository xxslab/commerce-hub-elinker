<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\Licensing\LicenseHubClient;
use App\Services\Licensing\LicenseHubUnavailableException;
use App\Services\Licensing\SubscriptionEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SubscriptionEntitlementTest extends TestCase
{
    use RefreshDatabase;

    private function service(LicenseHubClient $client): SubscriptionEntitlementService
    {
        return new SubscriptionEntitlementService($client);
    }

    /**
     * license_hub_workspace_id/entitlement_* are deliberately excluded from
     * Company::$fillable (only trusted code paths, never a form, may set
     * them) — create() would silently drop them, so tests must forceFill
     * the same way the real application code does.
     */
    private function makeCompany(string $email, array $extra = []): Company
    {
        $company = Company::create(['name' => 'Acme', 'email' => $email]);
        if ($extra) {
            $company->forceFill($extra)->save();
        }

        return $company->fresh();
    }

    public function test_refresh_updates_local_status_and_plan_on_success(): void
    {
        $company = $this->makeCompany('a@example.test', ['license_hub_workspace_id' => '1001']);

        $client = Mockery::mock(LicenseHubClient::class);
        $client->shouldReceive('checkEntitlement')->once()->with('1001')->andReturn([
            'active' => true, 'status' => 'active', 'plan_code' => 'pro',
            'features' => ['woocommerce_channels' => ['type' => 'limit', 'limit' => 5, 'usage' => 1]],
            'checked_at' => now()->toIso8601String(),
        ]);

        $this->service($client)->refresh($company);

        $company->refresh();
        self::assertSame('active', $company->entitlement_status);
        self::assertSame('pro', $company->entitlement_plan_code);
        self::assertSame('ok', $company->entitlement_sync_status);
        self::assertNotNull($company->entitlement_checked_at);
        self::assertSame(5, $company->entitlement_features['woocommerce_channels']['limit']);
    }

    public function test_refresh_does_nothing_for_a_company_without_a_linked_workspace(): void
    {
        $company = $this->makeCompany('b@example.test');

        $client = Mockery::mock(LicenseHubClient::class);
        $client->shouldNotReceive('checkEntitlement');

        $this->service($client)->refresh($company);

        self::assertNull($company->fresh()->entitlement_status);
    }

    /**
     * The core "License Hub down" guarantee: a failed/timed-out check marks
     * sync as degraded but must NEVER touch entitlement_status/plan_code —
     * an outage must never itself suspend an active company.
     */
    public function test_a_failed_check_marks_sync_degraded_but_never_changes_status_or_plan(): void
    {
        $company = $this->makeCompany('c@example.test', [
            'license_hub_workspace_id' => '1002',
            'entitlement_status' => 'active', 'entitlement_plan_code' => 'pro',
        ]);

        $client = Mockery::mock(LicenseHubClient::class);
        $client->shouldReceive('checkEntitlement')->once()->andThrow(new LicenseHubUnavailableException('timeout'));

        $this->service($client)->refresh($company);

        $company->refresh();
        self::assertSame('active', $company->entitlement_status, 'status must survive a License Hub outage unchanged');
        self::assertSame('pro', $company->entitlement_plan_code);
        self::assertSame('degraded', $company->entitlement_sync_status);
    }

    public function test_degraded_sync_does_not_block_an_otherwise_active_company(): void
    {
        $company = $this->makeCompany('d@example.test', [
            'license_hub_workspace_id' => '1003',
            'entitlement_status' => 'active', 'entitlement_sync_status' => 'degraded',
        ]);
        config(['commerce-hub.license_hub.enforce_gating' => true]);

        $service = $this->service(Mockery::mock(LicenseHubClient::class));

        self::assertTrue($service->isActive($company));
    }

    public function test_suspended_status_blocks_gated_actions_when_enforcement_is_on(): void
    {
        $company = $this->makeCompany('e@example.test', [
            'license_hub_workspace_id' => '1004',
            'entitlement_status' => 'suspended',
        ]);
        config(['commerce-hub.license_hub.enforce_gating' => true]);

        $service = $this->service(Mockery::mock(LicenseHubClient::class));

        self::assertFalse($service->isActive($company));
    }

    public function test_unlinked_company_is_never_gated_even_if_enforcement_is_on(): void
    {
        $company = $this->makeCompany('f@example.test');
        config(['commerce-hub.license_hub.enforce_gating' => true]);

        $service = $this->service(Mockery::mock(LicenseHubClient::class));

        self::assertTrue($service->isActive($company));
        self::assertFalse($service->isGatingApplicable($company));
    }

    public function test_suspended_company_is_not_gated_while_enforcement_flag_is_off(): void
    {
        $company = $this->makeCompany('g@example.test', [
            'license_hub_workspace_id' => '1005',
            'entitlement_status' => 'suspended',
        ]);
        config(['commerce-hub.license_hub.enforce_gating' => false]);

        $service = $this->service(Mockery::mock(LicenseHubClient::class));

        self::assertTrue($service->isActive($company));
    }
}

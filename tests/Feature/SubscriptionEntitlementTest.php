<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\Licensing\FeatureKeys;
use App\Services\Licensing\FeatureNotAllowedException;
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

    public function test_can_is_always_true_when_gating_is_not_applicable(): void
    {
        $company = $this->makeCompany('h@example.test');
        config(['commerce-hub.license_hub.enforce_gating' => true]);

        $service = $this->service(Mockery::mock(LicenseHubClient::class));

        self::assertTrue($service->can($company, FeatureKeys::CHANNEL_WOOCOMMERCE));
    }

    public function test_can_default_denies_an_unmapped_feature_key(): void
    {
        $company = $this->makeCompany('i@example.test', [
            'license_hub_workspace_id' => '2001',
            'entitlement_status' => 'active',
            'entitlement_features' => [],
        ]);
        config(['commerce-hub.license_hub.enforce_gating' => true]);

        $service = $this->service(Mockery::mock(LicenseHubClient::class));

        self::assertFalse($service->can($company, FeatureKeys::CHANNEL_WOOCOMMERCE));
    }

    public function test_can_is_true_for_an_enabled_boolean_feature(): void
    {
        $company = $this->makeCompany('j@example.test', [
            'license_hub_workspace_id' => '2002',
            'entitlement_status' => 'active',
            'entitlement_features' => [FeatureKeys::SYNC => ['type' => 'boolean', 'enabled' => true]],
        ]);
        config(['commerce-hub.license_hub.enforce_gating' => true]);

        $service = $this->service(Mockery::mock(LicenseHubClient::class));

        self::assertTrue($service->can($company, FeatureKeys::SYNC));
    }

    public function test_can_is_false_for_a_disabled_boolean_feature(): void
    {
        $company = $this->makeCompany('k@example.test', [
            'license_hub_workspace_id' => '2003',
            'entitlement_status' => 'active',
            'entitlement_features' => [FeatureKeys::SYNC => ['type' => 'boolean', 'enabled' => false]],
        ]);
        config(['commerce-hub.license_hub.enforce_gating' => true]);

        $service = $this->service(Mockery::mock(LicenseHubClient::class));

        self::assertFalse($service->can($company, FeatureKeys::SYNC));
    }

    public function test_can_respects_a_limit_type_feature_with_remaining_room(): void
    {
        $company = $this->makeCompany('l@example.test', [
            'license_hub_workspace_id' => '2004',
            'entitlement_status' => 'active',
            'entitlement_features' => [FeatureKeys::CHANNEL_WOOCOMMERCE => ['type' => 'limit', 'limit' => 3, 'usage' => 2, 'unlimited' => false]],
        ]);
        config(['commerce-hub.license_hub.enforce_gating' => true]);

        $service = $this->service(Mockery::mock(LicenseHubClient::class));

        self::assertTrue($service->can($company, FeatureKeys::CHANNEL_WOOCOMMERCE));
        self::assertSame(3, $service->limit($company, FeatureKeys::CHANNEL_WOOCOMMERCE));
        self::assertSame(2, $service->usage($company, FeatureKeys::CHANNEL_WOOCOMMERCE));
    }

    public function test_can_is_false_once_a_limit_type_feature_is_exhausted(): void
    {
        $company = $this->makeCompany('m@example.test', [
            'license_hub_workspace_id' => '2005',
            'entitlement_status' => 'active',
            'entitlement_features' => [FeatureKeys::CHANNEL_WOOCOMMERCE => ['type' => 'limit', 'limit' => 3, 'usage' => 3, 'unlimited' => false]],
        ]);
        config(['commerce-hub.license_hub.enforce_gating' => true]);

        $service = $this->service(Mockery::mock(LicenseHubClient::class));

        self::assertFalse($service->can($company, FeatureKeys::CHANNEL_WOOCOMMERCE));
    }

    public function test_can_is_true_for_an_unlimited_limit_type_feature(): void
    {
        $company = $this->makeCompany('n@example.test', [
            'license_hub_workspace_id' => '2006',
            'entitlement_status' => 'active',
            'entitlement_features' => [FeatureKeys::CHANNEL_WOOCOMMERCE => ['type' => 'limit', 'limit' => null, 'usage' => 9999, 'unlimited' => true]],
        ]);
        config(['commerce-hub.license_hub.enforce_gating' => true]);

        $service = $this->service(Mockery::mock(LicenseHubClient::class));

        self::assertTrue($service->can($company, FeatureKeys::CHANNEL_WOOCOMMERCE));
        self::assertNull($service->limit($company, FeatureKeys::CHANNEL_WOOCOMMERCE));
    }

    public function test_assert_allowed_throws_when_not_allowed(): void
    {
        $company = $this->makeCompany('o@example.test', [
            'license_hub_workspace_id' => '2007',
            'entitlement_status' => 'active',
            'entitlement_features' => [],
        ]);
        config(['commerce-hub.license_hub.enforce_gating' => true]);

        $service = $this->service(Mockery::mock(LicenseHubClient::class));

        $this->expectException(FeatureNotAllowedException::class);
        $service->assertAllowed($company, FeatureKeys::CHANNEL_ALLEGRO);
    }

    public function test_assert_allowed_does_not_throw_when_allowed(): void
    {
        $company = $this->makeCompany('p@example.test', [
            'license_hub_workspace_id' => '2008',
            'entitlement_status' => 'active',
            'entitlement_features' => [FeatureKeys::CHANNEL_ALLEGRO => ['type' => 'boolean', 'enabled' => true]],
        ]);
        config(['commerce-hub.license_hub.enforce_gating' => true]);

        $service = $this->service(Mockery::mock(LicenseHubClient::class));

        $service->assertAllowed($company, FeatureKeys::CHANNEL_ALLEGRO);
        $this->addToAssertionCount(1);
    }
}

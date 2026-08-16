<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyBillingAuditLog;
use App\Models\User;
use App\Services\Licensing\LicenseHubClient;
use App\Services\Licensing\LicenseHubUnavailableException;
use App\Services\Licensing\ProductLinkRejectedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BillingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_connect_using_a_valid_connection_code(): void
    {
        $company = Company::create(['name' => 'Acme', 'email' => 'a@example.test']);
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);

        $client = Mockery::mock(LicenseHubClient::class);
        $client->shouldReceive('consumeProductLink')->once()->with('valid-code')->andReturn('3001');
        $client->shouldReceive('checkEntitlement')->once()->with('3001')->andReturn(['active' => true, 'status' => 'active', 'plan_code' => 'pro', 'features' => []]);
        $this->app->instance(LicenseHubClient::class, $client);

        $response = $this->actingAs($user)->post('/settings/billing/connect', ['token' => 'valid-code']);

        $response->assertRedirect();
        self::assertSame('3001', $company->fresh()->license_hub_workspace_id);
        self::assertDatabaseHas('company_billing_audit_logs', [
            'company_id' => $company->id,
            'event' => 'billing.connected',
            'workspace_id' => '3001',
        ]);
    }

    public function test_a_raw_workspace_id_is_not_accepted_as_a_connection_code(): void
    {
        $company = Company::create(['name' => 'Acme', 'email' => 'raw@example.test']);
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);

        $client = Mockery::mock(LicenseHubClient::class);
        $client->shouldReceive('consumeProductLink')->once()->with('3001')
            ->andThrow(new ProductLinkRejectedException('Nieznany token.'));
        $this->app->instance(LicenseHubClient::class, $client);

        $response = $this->actingAs($user)->post('/settings/billing/connect', ['token' => '3001']);

        $response->assertSessionHasErrors('token');
        self::assertNull($company->fresh()->license_hub_workspace_id);
        self::assertDatabaseHas('company_billing_audit_logs', [
            'company_id' => $company->id,
            'event' => 'billing.connect_rejected',
        ]);
    }

    public function test_non_admin_cannot_connect(): void
    {
        $company = Company::create(['name' => 'Acme', 'email' => 'b@example.test']);
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'member']);

        $response = $this->actingAs($user)->post('/settings/billing/connect', ['token' => 'whatever']);

        $response->assertStatus(403);
        self::assertNull($company->fresh()->license_hub_workspace_id);
    }

    /**
     * A company cannot take over a workspace_id already claimed by another
     * company, even if License Hub itself returned that workspace_id (e.g.
     * an operator accidentally issued a second connection code for a
     * workspace already linked) — the eLinker-side uniqueness check and
     * DB unique constraint are a second line of defense on top of License
     * Hub's own single-use token.
     */
    public function test_a_workspace_already_linked_to_another_company_cannot_be_claimed(): void
    {
        $taken = Company::create(['name' => 'Other', 'email' => 'other@example.test']);
        $taken->forceFill(['license_hub_workspace_id' => '3003'])->save();
        $company = Company::create(['name' => 'Acme', 'email' => 'c@example.test']);
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);

        $client = Mockery::mock(LicenseHubClient::class);
        $client->shouldReceive('consumeProductLink')->once()->with('some-code')->andReturn('3003');
        $this->app->instance(LicenseHubClient::class, $client);

        $response = $this->actingAs($user)->post('/settings/billing/connect', ['token' => 'some-code']);

        $response->assertSessionHasErrors('token');
        self::assertNull($company->fresh()->license_hub_workspace_id);
        self::assertDatabaseHas('company_billing_audit_logs', [
            'company_id' => $company->id,
            'event' => 'billing.connect_conflict',
        ]);
    }

    public function test_a_hub_outage_during_connect_does_not_link_and_is_reported_as_retryable(): void
    {
        $company = Company::create(['name' => 'Acme', 'email' => 'outage@example.test']);
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);

        $client = Mockery::mock(LicenseHubClient::class);
        $client->shouldReceive('consumeProductLink')->once()->andThrow(new LicenseHubUnavailableException('timeout'));
        $this->app->instance(LicenseHubClient::class, $client);

        $response = $this->actingAs($user)->post('/settings/billing/connect', ['token' => 'some-code']);

        $response->assertSessionHasErrors('token');
        self::assertNull($company->fresh()->license_hub_workspace_id);
    }

    public function test_admin_can_disconnect_without_deleting_commercial_data(): void
    {
        $company = Company::create(['name' => 'Acme', 'email' => 'disc@example.test']);
        $company->forceFill([
            'license_hub_workspace_id' => '4001',
            'entitlement_status' => 'active',
            'entitlement_plan_code' => 'pro',
        ])->save();
        $channel = $company->salesChannels()->create([
            'type' => 'woocommerce',
            'name' => 'Main store',
            'status' => 'active',
        ]);
        $order = $company->orders()->create([
            'sales_channel_id' => $channel->id,
            'external_order_id' => 'ext-1',
            'source' => 'woocommerce',
            'total' => 10,
            'currency' => 'PLN',
        ]);
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);

        $response = $this->actingAs($user)->post('/settings/billing/disconnect');

        $response->assertRedirect();
        $fresh = $company->fresh();
        self::assertNull($fresh->license_hub_workspace_id);
        self::assertNull($fresh->entitlement_status);
        self::assertNull($fresh->entitlement_plan_code);
        self::assertDatabaseHas('commerce_orders', ['id' => $order->id]);
        self::assertDatabaseHas('sales_channels', ['id' => $channel->id]);
        self::assertDatabaseHas('company_billing_audit_logs', [
            'company_id' => $company->id,
            'event' => 'billing.disconnected',
            'workspace_id' => '4001',
        ]);
    }

    public function test_non_admin_cannot_disconnect(): void
    {
        $company = Company::create(['name' => 'Acme', 'email' => 'nd@example.test']);
        $company->forceFill(['license_hub_workspace_id' => '4002'])->save();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'member']);

        $response = $this->actingAs($user)->post('/settings/billing/disconnect');

        $response->assertStatus(403);
        self::assertSame('4002', $company->fresh()->license_hub_workspace_id);
    }

    public function test_billing_settings_page_only_shows_the_users_own_company(): void
    {
        $mine = Company::create(['name' => 'Mine', 'email' => 'mine@example.test']);
        $mine->forceFill(['license_hub_workspace_id' => '3004', 'entitlement_plan_code' => 'pro'])->save();
        $other = Company::create(['name' => 'Other', 'email' => 'other2@example.test']);
        $other->forceFill(['license_hub_workspace_id' => '3005', 'entitlement_plan_code' => 'business'])->save();
        $user = User::factory()->create(['company_id' => $mine->id]);

        $response = $this->actingAs($user)->get('/settings/billing');

        $response->assertOk();
        $response->assertSee('pro');
        $response->assertDontSee('business');
        $response->assertDontSee('3005');
    }

    public function test_billing_settings_page_only_shows_the_users_own_company_audit_log(): void
    {
        $mine = Company::create(['name' => 'Mine', 'email' => 'mine2@example.test']);
        $other = Company::create(['name' => 'Other', 'email' => 'other3@example.test']);
        CompanyBillingAuditLog::query()->create(['company_id' => $mine->id, 'event' => 'billing.connected', 'workspace_id' => '9001']);
        CompanyBillingAuditLog::query()->create(['company_id' => $other->id, 'event' => 'billing.connected', 'workspace_id' => '9002']);
        $user = User::factory()->create(['company_id' => $mine->id]);

        $response = $this->actingAs($user)->get('/settings/billing');

        $response->assertOk();
        $response->assertSee('9001');
        $response->assertDontSee('9002');
    }
}

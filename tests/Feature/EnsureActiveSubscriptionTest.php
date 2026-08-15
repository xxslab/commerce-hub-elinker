<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureActiveSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_company_cannot_add_a_new_woocommerce_channel_when_gating_enforced(): void
    {
        config(['commerce-hub.license_hub.enforce_gating' => true]);
        $company = Company::create(['name' => 'Acme', 'email' => 'a@example.test']);
        $company->forceFill(['license_hub_workspace_id' => '2001', 'entitlement_status' => 'suspended'])->save();
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->post('/channels/woocommerce', [
            'name' => 'Sklep', 'base_url' => 'https://shop.example.test',
            'consumer_key' => 'ck', 'consumer_secret' => 'cs',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        self::assertSame(0, SalesChannel::where('company_id', $company->id)->count());
    }

    public function test_suspended_company_can_still_view_orders_and_dashboard(): void
    {
        config(['commerce-hub.license_hub.enforce_gating' => true]);
        $company = Company::create(['name' => 'Acme', 'email' => 'b@example.test']);
        $company->forceFill(['license_hub_workspace_id' => '2002', 'entitlement_status' => 'suspended'])->save();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)->get('/')->assertOk();
        $this->actingAs($user)->get('/orders')->assertOk();
        $this->actingAs($user)->get('/settings/billing')->assertOk();
    }

    public function test_active_company_can_add_a_new_channel(): void
    {
        config(['commerce-hub.license_hub.enforce_gating' => true]);
        $company = Company::create(['name' => 'Acme', 'email' => 'c@example.test']);
        $company->forceFill(['license_hub_workspace_id' => '2003', 'entitlement_status' => 'active'])->save();
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->post('/channels/woocommerce', [
            'name' => 'Sklep', 'base_url' => 'https://shop.example.test',
            'consumer_key' => 'ck', 'consumer_secret' => 'cs',
        ]);

        $response->assertRedirect(route('channels.index'));
        self::assertSame(1, SalesChannel::where('company_id', $company->id)->count());
    }

    public function test_unlinked_company_is_never_blocked_regardless_of_enforcement_flag(): void
    {
        config(['commerce-hub.license_hub.enforce_gating' => true]);
        $company = Company::create(['name' => 'Acme', 'email' => 'd@example.test']);
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->post('/channels/woocommerce', [
            'name' => 'Sklep', 'base_url' => 'https://shop.example.test',
            'consumer_key' => 'ck', 'consumer_secret' => 'cs',
        ]);

        $response->assertRedirect(route('channels.index'));
        self::assertSame(1, SalesChannel::where('company_id', $company->id)->count());
    }

    public function test_suspended_company_is_not_blocked_while_enforcement_flag_is_off(): void
    {
        config(['commerce-hub.license_hub.enforce_gating' => false]);
        $company = Company::create(['name' => 'Acme', 'email' => 'e@example.test']);
        $company->forceFill(['license_hub_workspace_id' => '2004', 'entitlement_status' => 'suspended'])->save();
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->post('/channels/woocommerce', [
            'name' => 'Sklep', 'base_url' => 'https://shop.example.test',
            'consumer_key' => 'ck', 'consumer_secret' => 'cs',
        ]);

        $response->assertRedirect(route('channels.index'));
        self::assertSame(1, SalesChannel::where('company_id', $company->id)->count());
    }
}

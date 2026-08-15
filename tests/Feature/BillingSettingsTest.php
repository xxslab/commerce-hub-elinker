<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_link_a_workspace(): void
    {
        $company = Company::create(['name' => 'Acme', 'email' => 'a@example.test']);
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);

        $response = $this->actingAs($user)->post('/settings/billing/link', ['license_hub_workspace_id' => '3001']);

        $response->assertRedirect();
        self::assertSame('3001', $company->fresh()->license_hub_workspace_id);
    }

    public function test_non_admin_cannot_link_a_workspace(): void
    {
        $company = Company::create(['name' => 'Acme', 'email' => 'b@example.test']);
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'member']);

        $response = $this->actingAs($user)->post('/settings/billing/link', ['license_hub_workspace_id' => '3002']);

        $response->assertStatus(403);
        self::assertNull($company->fresh()->license_hub_workspace_id);
    }

    /**
     * A company cannot take over a workspace_id already claimed by another
     * company — the DB unique constraint + validation rule prevent it,
     * rather than silently letting two companies share one entitlement.
     */
    public function test_a_workspace_already_linked_to_another_company_cannot_be_claimed(): void
    {
        $taken = Company::create(['name' => 'Other', 'email' => 'other@example.test']);
        $taken->forceFill(['license_hub_workspace_id' => '3003'])->save();
        $company = Company::create(['name' => 'Acme', 'email' => 'c@example.test']);
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);

        $response = $this->actingAs($user)->post('/settings/billing/link', ['license_hub_workspace_id' => '3003']);

        $response->assertSessionHasErrors('license_hub_workspace_id');
        self::assertNull($company->fresh()->license_hub_workspace_id);
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
}

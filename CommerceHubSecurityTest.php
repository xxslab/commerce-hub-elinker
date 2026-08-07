<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\Company;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CommerceHubSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_user_cannot_view_another_company_order(): void
    {
        $first = Company::create(['name' => 'First', 'email' => 'first@example.test']);
        $second = Company::create(['name' => 'Second', 'email' => 'second@example.test']);
        $user = User::factory()->create(['company_id' => $first->id]);
        $channel = SalesChannel::create(['company_id' => $second->id, 'type' => 'woocommerce', 'name' => 'Other', 'is_enabled' => true]);
        $order = CommerceOrder::create([
            'company_id' => $second->id, 'sales_channel_id' => $channel->id,
            'source' => 'woocommerce', 'external_order_id' => 'other-1',
        ]);

        $this->actingAs($user)->get('/orders/' . $order->id)->assertNotFound();
    }

    public function test_login_uses_hashed_password_and_session_regeneration(): void
    {
        $user = User::factory()->create(['password' => Hash::make('a-secure-password')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'a-secure-password'])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }
}

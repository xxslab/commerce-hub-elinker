<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\Company;
use App\Models\SalesChannel;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_unified_orders_list_shows_all_channels_with_shipments(): void
    {
        $company = Company::create(['name' => 'Acme', 'email' => 'acme@example.test']);
        $user = User::factory()->create(['company_id' => $company->id]);

        $woo = SalesChannel::create(['company_id' => $company->id, 'type' => 'woocommerce', 'name' => 'Sklep WP', 'is_enabled' => true]);
        $allegro = SalesChannel::create(['company_id' => $company->id, 'type' => 'allegro', 'name' => 'Allegro Konto', 'is_enabled' => true]);
        $ebay = SalesChannel::create(['company_id' => $company->id, 'type' => 'ebay', 'name' => 'eBay Konto', 'is_enabled' => true]);

        $wooOrder = CommerceOrder::create([
            'company_id' => $company->id, 'sales_channel_id' => $woo->id,
            'source' => 'woocommerce', 'external_order_id' => 'w-1', 'order_number' => 'W1',
            'status_normalized' => 'PROCESSING', 'payment_status' => 'paid',
            'shipping_country' => 'PL', 'total' => 99.90, 'currency' => 'PLN',
            'ordered_at' => now(),
        ]);
        CommerceOrder::create([
            'company_id' => $company->id, 'sales_channel_id' => $allegro->id,
            'source' => 'allegro', 'external_order_id' => 'a-1', 'order_number' => 'A1',
            'status_normalized' => 'NEW', 'payment_status' => 'unknown',
            'shipping_country' => 'DE', 'total' => 50, 'currency' => 'PLN',
            'ordered_at' => now(),
        ]);
        CommerceOrder::create([
            'company_id' => $company->id, 'sales_channel_id' => $ebay->id,
            'source' => 'ebay', 'external_order_id' => 'e-1', 'order_number' => 'E1',
            'status_normalized' => 'SHIPPED', 'payment_status' => 'paid',
            'shipping_country' => 'US', 'total' => 20, 'currency' => 'USD',
            'ordered_at' => now(),
        ]);

        Shipment::create([
            'company_id' => $company->id,
            'commerce_order_id' => $wooOrder->id,
            'carrier' => 'inpost',
            'tracking_number' => 'TRACK123',
            'status' => 'SHIPPED',
        ]);

        $response = $this->actingAs($user)->get('/orders');

        $response->assertOk();
        $response->assertSee('WooCommerce');
        $response->assertSee('Allegro');
        $response->assertSee('eBay');
        $response->assertSee('TRACK123');
        $response->assertSee('W1');
        $response->assertSee('A1');
        $response->assertSee('E1');
    }

    public function test_orders_list_only_shows_own_company_orders(): void
    {
        $mine = Company::create(['name' => 'Mine', 'email' => 'mine@example.test']);
        $other = Company::create(['name' => 'Other', 'email' => 'other@example.test']);
        $user = User::factory()->create(['company_id' => $mine->id]);

        $otherChannel = SalesChannel::create(['company_id' => $other->id, 'type' => 'woocommerce', 'name' => 'Other shop', 'is_enabled' => true]);
        CommerceOrder::create([
            'company_id' => $other->id, 'sales_channel_id' => $otherChannel->id,
            'source' => 'woocommerce', 'external_order_id' => 'foreign-1', 'order_number' => 'FOREIGN-1',
            'ordered_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/orders');

        $response->assertOk();
        $response->assertDontSee('FOREIGN-1');
    }

    public function test_orders_list_channel_filter(): void
    {
        $company = Company::create(['name' => 'Acme', 'email' => 'acme2@example.test']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $woo = SalesChannel::create(['company_id' => $company->id, 'type' => 'woocommerce', 'name' => 'Sklep WP', 'is_enabled' => true]);
        $allegro = SalesChannel::create(['company_id' => $company->id, 'type' => 'allegro', 'name' => 'Allegro', 'is_enabled' => true]);

        CommerceOrder::create(['company_id' => $company->id, 'sales_channel_id' => $woo->id, 'source' => 'woocommerce', 'external_order_id' => 'w-2', 'order_number' => 'W2', 'ordered_at' => now()]);
        CommerceOrder::create(['company_id' => $company->id, 'sales_channel_id' => $allegro->id, 'source' => 'allegro', 'external_order_id' => 'a-2', 'order_number' => 'A2', 'ordered_at' => now()]);

        $response = $this->actingAs($user)->get('/orders?channel_id=' . $woo->id);

        $response->assertOk();
        $response->assertSee('W2');
        $response->assertDontSee('A2');
    }
}

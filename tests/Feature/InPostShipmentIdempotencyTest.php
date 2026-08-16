<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\Company;
use App\Models\SalesChannel;
use App\Models\Shipment;
use App\Services\Carriers\InPost\InPostClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InPostShipmentIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_double_create_shipment_does_not_create_a_duplicate(): void
    {
        Http::fake([
            '*/shipments' => Http::response(['id' => 999, 'tracking_number' => 'INP123', 'status' => 'created'], 200),
        ]);

        $company = Company::create(['name' => 'Acme', 'email' => 'inpost@example.test']);
        $channel = SalesChannel::create(['company_id' => $company->id, 'type' => 'woocommerce', 'name' => 'Sklep', 'is_enabled' => true]);
        $order = CommerceOrder::create([
            'company_id' => $company->id, 'sales_channel_id' => $channel->id,
            'source' => 'woocommerce', 'external_order_id' => 'o-1', 'order_number' => 'O1',
            'customer_name' => 'Jan Kowalski', 'customer_email' => 'jan@example.test',
            'shipping_address' => ['city' => 'Warszawa', 'postcode' => '00-001', 'country' => 'PL'],
        ]);

        $client = app(InPostClient::class);
        $first = $client->createShipment($order, ['template' => 'small', 'weight' => 1]);
        $second = $client->createShipment($order, ['template' => 'small', 'weight' => 1]);

        self::assertSame($first->id, $second->id);
        self::assertSame(1, Shipment::where('commerce_order_id', $order->id)->count());
        Http::assertSentCount(1);
    }

    public function test_locker_shipment_uses_target_point_and_omits_street_address(): void
    {
        $captured = null;
        Http::fake(function ($request) use (&$captured) {
            $captured = $request;
            return Http::response(['id' => 1000, 'tracking_number' => 'INP-LOCKER', 'status' => 'created'], 200);
        });

        $company = Company::create(['name' => 'Acme2', 'email' => 'inpost2@example.test']);
        $channel = SalesChannel::create(['company_id' => $company->id, 'type' => 'woocommerce', 'name' => 'Sklep', 'is_enabled' => true]);
        $order = CommerceOrder::create([
            'company_id' => $company->id, 'sales_channel_id' => $channel->id,
            'source' => 'woocommerce', 'external_order_id' => 'o-2', 'order_number' => 'O2',
            'customer_name' => 'Anna Nowak', 'customer_email' => 'anna@example.test',
        ]);

        app(InPostClient::class)->createShipment($order, ['point' => 'WAW01A']);

        self::assertNotNull($captured);
        $body = $captured->data();
        self::assertSame('WAW01A', $body['custom_attributes']['target_point']);
        self::assertSame('inpost_locker_standard', $body['service']);
        self::assertArrayNotHasKey('address', $body['receiver']);
    }
}

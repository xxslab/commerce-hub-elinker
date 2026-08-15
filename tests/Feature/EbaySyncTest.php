<?php

namespace Tests\Feature;

use App\Jobs\PushTrackingToSourceJob;
use App\Jobs\SyncSalesChannelOrdersJob;
use App\Models\CommerceOrder;
use App\Models\Company;
use App\Models\SalesChannel;
use App\Models\Shipment;
use App\Services\Integrations\Ebay\EbayOrderSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EbaySyncTest extends TestCase
{
    use RefreshDatabase;

    private function fakeOrder(string $id, string $fulfillment = 'NOT_STARTED', string $payment = 'PAID'): array
    {
        return [
            'orderId' => $id,
            'legacyOrderId' => $id,
            'orderFulfillmentStatus' => $fulfillment,
            'orderPaymentStatus' => $payment,
            'buyer' => ['username' => 'buyer1', 'email' => 'buyer1@example.test'],
            'pricingSummary' => ['total' => ['value' => '45.00', 'currency' => 'USD']],
            'creationDate' => '2026-01-01T10:00:00Z',
            'lastModifiedDate' => '2026-01-01T10:05:00Z',
            'lineItems' => [
                ['lineItemId' => 'li-1', 'legacyItemId' => 'legacy-1', 'sku' => 'SKU-E', 'title' => 'Produkt eBay', 'quantity' => 1, 'lineItemCost' => ['value' => '45.00']],
            ],
        ];
    }

    private function channel(): SalesChannel
    {
        $company = Company::create(['name' => 'Acme', 'email' => 'ebay-' . uniqid() . '@example.test']);
        $channel = SalesChannel::create(['company_id' => $company->id, 'type' => 'ebay', 'name' => 'eBay konto', 'is_enabled' => true]);
        $channel->setCredentials(['access_token' => 'valid-token', 'refresh_token' => 'refresh-token']);
        $channel->save();

        return $channel;
    }

    public function test_paginated_orders_are_all_imported(): void
    {
        $page1 = array_map(fn ($i) => $this->fakeOrder("e-$i"), range(1, 200));
        $page2 = [$this->fakeOrder('e-201')];

        Http::fake([
            '*/sell/fulfillment/v1/order*' => Http::sequence()
                ->push(['orders' => $page1])
                ->push(['orders' => $page2]),
        ]);

        $channel = $this->channel();
        $stats = app(EbayOrderSyncService::class)->sync($channel);

        self::assertSame(201, $stats['fetched']);
        self::assertSame(201, $stats['created']);
        self::assertSame(201, CommerceOrder::where('sales_channel_id', $channel->id)->count());
    }

    public function test_repeated_sync_does_not_duplicate_orders_and_updates_status(): void
    {
        Http::fake([
            '*/sell/fulfillment/v1/order*' => Http::sequence()
                ->push(['orders' => [$this->fakeOrder('e-500')]])
                ->push(['orders' => [$this->fakeOrder('e-500', 'FULFILLED')]]),
        ]);

        $channel = $this->channel();
        $service = app(EbayOrderSyncService::class);

        $first = $service->sync($channel);
        self::assertSame(1, $first['created']);

        $channel->refresh();
        $second = $service->sync($channel);
        self::assertSame(0, $second['created']);
        self::assertSame(1, $second['updated']);

        self::assertSame(1, CommerceOrder::where('sales_channel_id', $channel->id)->count());
        self::assertSame('SHIPPED', CommerceOrder::sole()->status_normalized);
    }

    public function test_payment_status_is_not_mixed_with_fulfilment_status(): void
    {
        Http::fake([
            '*/sell/fulfillment/v1/order*' => Http::sequence()
                ->push(['orders' => [$this->fakeOrder('e-600', 'NOT_STARTED', 'PAID')]])
                ->push(['orders' => []]),
        ]);

        $channel = $this->channel();
        app(EbayOrderSyncService::class)->sync($channel);

        $order = CommerceOrder::sole();
        self::assertSame('PAID', $order->payment_status);
        self::assertSame('NEW', $order->status_normalized);
    }

    public function test_401_marks_channel_as_authentication_error(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid_token'], 401)]);

        $channel = $this->channel();
        (new SyncSalesChannelOrdersJob($channel->id))->handle();

        $channel->refresh();
        self::assertSame('authentication_error', $channel->sync_status);
        self::assertSame('ebay_authentication', $channel->last_error_code);
    }

    public function test_403_marks_channel_as_authentication_error(): void
    {
        Http::fake(['*' => Http::response(['error' => 'forbidden'], 403)]);

        $channel = $this->channel();
        (new SyncSalesChannelOrdersJob($channel->id))->handle();

        $channel->refresh();
        self::assertSame('authentication_error', $channel->sync_status);
        self::assertSame('ebay_authentication', $channel->last_error_code);
    }

    public function test_429_is_reported_as_rate_limited(): void
    {
        Http::fake(['*' => Http::response(['error' => 'rate limited'], 429)]);

        $channel = $this->channel();
        try {
            (new SyncSalesChannelOrdersJob($channel->id))->handle();
        } catch (\Throwable $e) {
            // expected re-throw
        }

        $channel->refresh();
        self::assertSame('rate_limited', $channel->sync_status);
        self::assertSame('ebay_rate_limited', $channel->last_error_code);
    }

    public function test_502_is_reported_as_ebay_error(): void
    {
        Http::fake(['*' => Http::response('Bad Gateway', 502)]);

        $channel = $this->channel();
        try {
            (new SyncSalesChannelOrdersJob($channel->id))->handle();
        } catch (\Throwable $e) {
            // expected re-throw
        }

        $channel->refresh();
        self::assertSame('ebay_http_502', $channel->last_error_code);
        self::assertStringContainsString('eBay', $channel->last_error);
    }

    public function test_push_tracking_sends_real_line_item_ids_and_configured_carrier_code(): void
    {
        config(['commerce-hub.ebay.carrier_codes.inpost' => 'InPostPL']);
        Http::fake(['*/shipping_fulfillment' => Http::response(['fulfillmentId' => 'f-1'], 200)]);

        $company = Company::create(['name' => 'Acme2', 'email' => 'ebay-push-' . uniqid() . '@example.test']);
        $channel = SalesChannel::create(['company_id' => $company->id, 'type' => 'ebay', 'name' => 'eBay', 'is_enabled' => true]);
        $channel->setCredentials(['access_token' => 'token']);
        $channel->save();

        $order = CommerceOrder::create([
            'company_id' => $company->id, 'sales_channel_id' => $channel->id,
            'source' => 'ebay', 'external_order_id' => 'e-777', 'order_number' => 'E777',
        ]);
        $order->items()->create([
            'external_product_id' => 'legacy-1',
            'name' => 'Produkt', 'quantity' => 3, 'price' => 10, 'total' => 30,
            'raw_payload' => ['lineItemId' => 'li-real-1', 'legacyItemId' => 'legacy-1'],
        ]);
        $shipment = Shipment::create([
            'company_id' => $company->id, 'commerce_order_id' => $order->id,
            'carrier' => 'inpost', 'tracking_number' => 'TRACK-E1', 'status' => 'SHIPPED',
        ]);

        (new PushTrackingToSourceJob($shipment->id))->handle();

        Http::assertSent(function ($request) {
            $body = $request->data();
            return str_ends_with($request->url(), 'e-777/shipping_fulfillment')
                && $body['shippingCarrierCode'] === 'InPostPL'
                && $body['trackingNumber'] === 'TRACK-E1'
                && $body['lineItems'][0]['lineItemId'] === 'li-real-1'
                && $body['lineItems'][0]['quantity'] === 3;
        });
    }
}

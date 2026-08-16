<?php

namespace Tests\Feature;

use App\Jobs\SyncSalesChannelOrdersJob;
use App\Models\CommerceOrder;
use App\Models\Company;
use App\Models\SalesChannel;
use App\Services\Integrations\Allegro\AllegroOrderSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AllegroSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Sales channel ids can be reused across tests once RefreshDatabase
        // rolls back the sqlite sequence; the array cache store does not
        // reset itself, so stale allegro-carriers:{id} entries from an
        // earlier test could otherwise leak into this one.
        Cache::flush();
    }

    private function fakeOrder(string $id, string $status = 'NEW', string $paymentStatus = 'PENDING'): array
    {
        return [
            'id' => $id,
            'status' => $status,
            'buyer' => ['login' => 'kupujacy', 'email' => 'kupujacy@example.test'],
            'payment' => ['status' => $paymentStatus],
            'delivery' => ['status' => 'PENDING', 'address' => ['countryCode' => 'PL', 'phoneNumber' => '600000000']],
            'summary' => ['totalToPay' => ['amount' => '120.00', 'currency' => 'PLN']],
            'boughtAt' => '2026-01-01T10:00:00Z',
            'updatedAt' => '2026-01-01T10:05:00Z',
            'lineItems' => [
                ['offer' => ['id' => 'off-1', 'name' => 'Produkt Allegro', 'external' => ['id' => 'SKU-A']], 'quantity' => 2, 'price' => ['amount' => '60.00']],
            ],
        ];
    }

    private function channel(): SalesChannel
    {
        $company = Company::create(['name' => 'Acme', 'email' => 'allegro-' . uniqid() . '@example.test']);
        $channel = SalesChannel::create(['company_id' => $company->id, 'type' => 'allegro', 'name' => 'Allegro konto', 'is_enabled' => true]);
        $channel->setCredentials(['access_token' => 'valid-token', 'refresh_token' => 'refresh-token']);
        $channel->save();

        return $channel;
    }

    public function test_paginated_orders_are_all_imported(): void
    {
        $page1 = array_map(fn ($i) => $this->fakeOrder("a-$i"), range(1, 100));
        $page2 = [$this->fakeOrder('a-101')];

        Http::fake([
            '*/order/checkout-forms*' => Http::sequence()
                ->push(['checkoutForms' => $page1])
                ->push(['checkoutForms' => $page2]),
        ]);

        $channel = $this->channel();
        $stats = app(AllegroOrderSyncService::class)->sync($channel);

        self::assertSame(101, $stats['fetched']);
        self::assertSame(101, $stats['created']);
        self::assertSame(101, CommerceOrder::where('sales_channel_id', $channel->id)->count());
    }

    public function test_repeated_sync_does_not_duplicate_orders(): void
    {
        Http::fake([
            '*/order/checkout-forms*' => Http::sequence()
                ->push(['checkoutForms' => [$this->fakeOrder('a-500')]])
                ->push(['checkoutForms' => [$this->fakeOrder('a-500', 'SENT', 'PAID')]]),
        ]);

        $channel = $this->channel();
        $service = app(AllegroOrderSyncService::class);

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
            '*/order/checkout-forms*' => Http::sequence()
                ->push(['checkoutForms' => [$this->fakeOrder('a-600', 'READY_FOR_PROCESSING', 'PAID')]])
                ->push(['checkoutForms' => []]),
        ]);

        $channel = $this->channel();
        app(AllegroOrderSyncService::class)->sync($channel);

        $order = CommerceOrder::sole();
        self::assertSame('PAID', $order->payment_status);
        self::assertSame('PAID', $order->status_normalized);
        self::assertNotSame($order->payment_status, 'READY_FOR_PROCESSING');
    }

    public function test_401_marks_channel_as_authentication_error(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid_token'], 401)]);

        $channel = $this->channel();
        (new SyncSalesChannelOrdersJob($channel->id))->handle();

        $channel->refresh();
        self::assertSame('authentication_error', $channel->sync_status);
        self::assertSame('allegro_authentication', $channel->last_error_code);
    }

    public function test_403_marks_channel_as_authentication_error(): void
    {
        Http::fake(['*' => Http::response(['error' => 'forbidden'], 403)]);

        $channel = $this->channel();
        (new SyncSalesChannelOrdersJob($channel->id))->handle();

        $channel->refresh();
        self::assertSame('authentication_error', $channel->sync_status);
        self::assertSame('allegro_authentication', $channel->last_error_code);
    }

    public function test_429_is_reported_as_rate_limited(): void
    {
        Http::fake(['*' => Http::response(['error' => 'rate limited'], 429)]);

        $channel = $this->channel();
        try {
            (new SyncSalesChannelOrdersJob($channel->id))->handle();
        } catch (\Throwable $e) {
            // job re-throws on non-auth errors so the queue can retry
        }

        $channel->refresh();
        self::assertSame('rate_limited', $channel->sync_status);
        self::assertSame('allegro_rate_limited', $channel->last_error_code);
    }

    public function test_500_is_reported_as_allegro_error(): void
    {
        Http::fake(['*' => Http::response(['error' => 'server error'], 500)]);

        $channel = $this->channel();
        try {
            (new SyncSalesChannelOrdersJob($channel->id))->handle();
        } catch (\Throwable $e) {
            // expected re-throw
        }

        $channel->refresh();
        self::assertSame('allegro_http_500', $channel->last_error_code);
        self::assertStringContainsString('Allegro', $channel->last_error);
    }

    public function test_carrier_is_resolved_from_live_carrier_list(): void
    {
        Http::fake([
            '*/order/carriers' => Http::response(['carriers' => [
                ['id' => 'aaaa-bbbb', 'name' => 'InPost Paczkomaty'],
                ['id' => 'cccc-dddd', 'name' => 'DPD'],
            ]], 200),
            '*/shipments' => Http::response(['id' => 'shp-1'], 200),
        ]);

        $channel = $this->channel();
        $result = app(\App\Services\Integrations\Allegro\AllegroClient::class, ['channel' => $channel])
            ->addShipment('checkout-1', 'inpost', 'TRACK1');

        self::assertSame('shp-1', $result['id']);
        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/shipments')
                && $request['carrierId'] === 'aaaa-bbbb'
                && $request['waybill'] === 'TRACK1';
        });
    }

    public function test_carrier_falls_back_to_other_when_not_found_in_live_list(): void
    {
        Http::fake([
            '*/order/carriers' => Http::response(['carriers' => [
                ['id' => 'cccc-dddd', 'name' => 'DPD'],
            ]], 200),
            '*/shipments' => Http::response(['id' => 'shp-2'], 200),
        ]);

        $channel = $this->channel();
        app(\App\Services\Integrations\Allegro\AllegroClient::class, ['channel' => $channel])
            ->addShipment('checkout-2', 'inpost', 'TRACK2');

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/shipments')
                && $request['carrierId'] === 'OTHER'
                && $request['carrierName'] === 'inpost';
        });
    }
}

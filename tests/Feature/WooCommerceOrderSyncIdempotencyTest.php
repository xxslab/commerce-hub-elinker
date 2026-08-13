<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\Company;
use App\Models\SalesChannel;
use App\Services\Integrations\WooCommerce\WooCommerceOrderSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WooCommerceOrderSyncIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function fakeOrder(int $id, string $status = 'processing'): array
    {
        return [
            'id' => $id,
            'number' => (string) $id,
            'status' => $status,
            'currency' => 'PLN',
            'total' => '150.00',
            'shipping_total' => '10.00',
            'discount_total' => '0.00',
            'total_tax' => '5.00',
            'date_paid' => '2026-01-01T10:00:00',
            'date_created' => '2026-01-01T09:00:00',
            'date_modified' => '2026-01-01T10:00:00',
            'billing' => ['first_name' => 'Jan', 'last_name' => 'Kowalski', 'email' => 'jan@example.test', 'country' => 'PL'],
            'shipping' => ['country' => 'PL'],
            'line_items' => [
                ['product_id' => 1, 'sku' => 'SKU1', 'name' => 'Produkt', 'quantity' => 1, 'price' => 140, 'total' => 140, 'total_tax' => 5],
            ],
        ];
    }

    public function test_repeated_sync_does_not_duplicate_the_same_order(): void
    {
        $company = Company::create(['name' => 'Acme', 'email' => 'idempotency@example.test']);
        $channel = SalesChannel::create([
            'company_id' => $company->id, 'type' => 'woocommerce', 'name' => 'Sklep',
            'base_url' => 'https://shop.example.test', 'is_enabled' => true,
        ]);
        $channel->setCredentials(['consumer_key' => 'ck', 'consumer_secret' => 'cs']);
        $channel->save();

        Http::fake([
            'https://shop.example.test/wp-json/wc/v3/orders*' => Http::sequence()
                ->push([$this->fakeOrder(501)])
                ->push([$this->fakeOrder(501, 'completed')]),
        ]);

        $service = app(WooCommerceOrderSyncService::class);
        $stats1 = $service->sync($channel);
        self::assertSame(1, $stats1['created']);
        self::assertSame(1, CommerceOrder::count());

        $channel->refresh();
        $stats2 = $service->sync($channel);
        self::assertSame(0, $stats2['created']);
        self::assertSame(1, $stats2['updated']);
        self::assertSame(1, CommerceOrder::count(), 'A second sync of the same external order must not create a duplicate row.');

        $order = CommerceOrder::sole();
        self::assertSame('COMPLETED', $order->status_normalized);
    }

    public function test_webhook_and_polling_sync_share_the_same_idempotency_key(): void
    {
        $company = Company::create(['name' => 'Acme2', 'email' => 'idempotency2@example.test']);
        $channel = SalesChannel::create([
            'company_id' => $company->id, 'type' => 'woocommerce', 'name' => 'Sklep',
            'base_url' => 'https://shop.example.test', 'is_enabled' => true,
        ]);
        $channel->setCredentials(['consumer_key' => 'ck', 'consumer_secret' => 'cs']);
        $channel->save();

        $service = app(WooCommerceOrderSyncService::class);
        $service->upsertFromWebhook($channel, $this->fakeOrder(777));
        $service->upsertFromWebhook($channel, $this->fakeOrder(777, 'completed'));

        self::assertSame(1, CommerceOrder::where('sales_channel_id', $channel->id)->count());
        self::assertSame('COMPLETED', CommerceOrder::sole()->status_normalized);
    }

    public function test_stale_out_of_order_webhook_does_not_overwrite_newer_data(): void
    {
        $company = Company::create(['name' => 'Acme3', 'email' => 'idempotency3@example.test']);
        $channel = SalesChannel::create([
            'company_id' => $company->id, 'type' => 'woocommerce', 'name' => 'Sklep',
            'base_url' => 'https://shop.example.test', 'is_enabled' => true,
        ]);
        $channel->setCredentials(['consumer_key' => 'ck', 'consumer_secret' => 'cs']);
        $channel->save();

        $service = app(WooCommerceOrderSyncService::class);

        $newer = $this->fakeOrder(888, 'completed');
        $newer['date_modified'] = '2026-01-05T12:00:00';
        $service->upsertFromWebhook($channel, $newer);

        $stale = $this->fakeOrder(888, 'processing');
        $stale['date_modified'] = '2026-01-01T08:00:00';
        $service->upsertFromWebhook($channel, $stale);

        self::assertSame('COMPLETED', CommerceOrder::sole()->status_normalized);
    }
}

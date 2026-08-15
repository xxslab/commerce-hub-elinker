<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\Company;
use App\Models\SalesChannel;
use App\Models\Shipment;
use App\Services\Carriers\InPost\InPostClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InPostErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    private function order(): CommerceOrder
    {
        $company = Company::create(['name' => 'Acme', 'email' => 'inpost-err-' . uniqid() . '@example.test']);
        $channel = SalesChannel::create(['company_id' => $company->id, 'type' => 'woocommerce', 'name' => 'Sklep', 'is_enabled' => true]);

        return CommerceOrder::create([
            'company_id' => $company->id, 'sales_channel_id' => $channel->id,
            'source' => 'woocommerce', 'external_order_id' => 'o-' . uniqid(), 'order_number' => 'O-' . uniqid(),
            'customer_name' => 'Jan Kowalski', 'customer_email' => 'jan@example.test',
            'shipping_address' => ['city' => 'Warszawa', 'postcode' => '00-001', 'country' => 'PL'],
        ]);
    }

    public function test_401_gives_actionable_message_and_creates_no_shipment(): void
    {
        Http::fake(['*/shipments' => Http::response(['error' => 'unauthorized'], 401)]);
        $order = $this->order();

        try {
            app(InPostClient::class)->createShipment($order, []);
            self::fail('Expected exception was not thrown.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Token InPost', $e->getMessage());
        }

        self::assertSame(0, Shipment::where('commerce_order_id', $order->id)->count());
    }

    public function test_422_bad_address_gives_actionable_message_and_creates_no_shipment(): void
    {
        Http::fake(['*/shipments' => Http::response(['status' => 422, 'errors' => ['receiver.address.post_code' => 'invalid']], 422)]);
        $order = $this->order();

        try {
            app(InPostClient::class)->createShipment($order, []);
            self::fail('Expected exception was not thrown.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('odrzucił dane przesyłki', $e->getMessage());
        }

        self::assertSame(0, Shipment::where('commerce_order_id', $order->id)->count());
    }

    public function test_422_bad_paczkomat_point_gives_actionable_message(): void
    {
        Http::fake(['*/shipments' => Http::response(['status' => 422, 'errors' => ['custom_attributes.target_point' => 'not found']], 422)]);
        $order = $this->order();

        try {
            app(InPostClient::class)->createShipment($order, ['point' => 'NOTREAL99']);
            self::fail('Expected exception was not thrown.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Paczkomatu', $e->getMessage());
        }

        self::assertSame(0, Shipment::where('commerce_order_id', $order->id)->count());
    }

    public function test_429_gives_rate_limit_message(): void
    {
        Http::fake(['*/shipments' => Http::response(['error' => 'too many requests'], 429)]);
        $order = $this->order();

        try {
            app(InPostClient::class)->createShipment($order, []);
            self::fail('Expected exception was not thrown.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('rate limit', $e->getMessage());
        }
    }

    public function test_500_gives_server_error_message(): void
    {
        Http::fake(['*/shipments' => Http::response(['error' => 'internal'], 500)]);
        $order = $this->order();

        try {
            app(InPostClient::class)->createShipment($order, []);
            self::fail('Expected exception was not thrown.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('niedostępne', $e->getMessage());
        }

        self::assertSame(0, Shipment::where('commerce_order_id', $order->id)->count());
    }

    public function test_connection_timeout_does_not_create_a_shipment(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });
        $order = $this->order();

        try {
            app(InPostClient::class)->createShipment($order, []);
            self::fail('Expected exception was not thrown.');
        } catch (\Throwable $e) {
            // A network-level failure surfaces as-is (no HTTP response to classify);
            // the important guarantee is that nothing was persisted, so a later
            // attempt (see test_a_failed_attempt_does_not_block_a_later_successful_retry,
            // in a fresh test with its own Http::fake) can still succeed.
        }

        self::assertSame(0, Shipment::where('commerce_order_id', $order->id)->count());
    }

    public function test_a_failed_attempt_does_not_block_a_later_successful_retry(): void
    {
        Http::fake([
            '*/shipments' => Http::sequence()
                ->push(['error' => 'internal'], 500)
                ->push(['error' => 'internal'], 500)
                ->push(['error' => 'internal'], 500)
                ->push(['id' => 42, 'tracking_number' => 'RETRY-OK', 'status' => 'created'], 200),
        ]);

        $order = $this->order();

        try {
            app(InPostClient::class)->createShipment($order, []);
            self::fail('Expected the first attempt to exhaust retries and fail.');
        } catch (\RuntimeException $e) {
            // expected: retry-eligible 500 exhausts its 3 attempts, then fails
        }

        self::assertSame(0, Shipment::where('commerce_order_id', $order->id)->count());

        $shipment = app(InPostClient::class)->createShipment($order, []);
        self::assertSame('RETRY-OK', $shipment->tracking_number);
        self::assertSame(1, Shipment::where('commerce_order_id', $order->id)->count());
    }
}

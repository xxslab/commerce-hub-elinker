<?php

namespace Tests\Feature;

use App\Models\CommerceOrder;
use App\Models\Company;
use App\Models\SalesChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WooCommerceWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function webhookOrder(int $id, string $status = 'processing'): array
    {
        return [
            'id' => $id,
            'number' => (string) $id,
            'status' => $status,
            'currency' => 'PLN',
            'total' => '75.00',
            'date_created' => '2026-01-01T09:00:00',
            'date_modified' => '2026-01-01T09:05:00',
            'billing' => ['first_name' => 'Ola', 'last_name' => 'Test', 'email' => 'ola@example.test', 'country' => 'PL'],
            'shipping' => ['country' => 'PL'],
            'line_items' => [],
        ];
    }

    private function channel(): SalesChannel
    {
        $company = Company::create(['name' => 'Acme', 'email' => 'webhook@example.test']);
        $channel = SalesChannel::create(['company_id' => $company->id, 'type' => 'woocommerce', 'name' => 'Sklep', 'is_enabled' => true]);
        $channel->setWebhookSecret('test-secret-value');
        $channel->save();

        return $channel;
    }

    private function sign(string $body, string $secret): string
    {
        return base64_encode(hash_hmac('sha256', $body, $secret, true));
    }

    public function test_valid_signature_upserts_order(): void
    {
        $channel = $this->channel();
        $payload = $this->webhookOrder(4001);
        $body = json_encode($payload);

        $response = $this->call('POST', '/api/webhooks/woocommerce/' . $channel->id, [], [], [], [
            'HTTP_X-WC-Webhook-Signature' => $this->sign($body, 'test-secret-value'),
            'HTTP_X-WC-Webhook-Topic' => 'order.updated',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertOk();
        self::assertSame(1, CommerceOrder::where('sales_channel_id', $channel->id)->where('external_order_id', '4001')->count());
    }

    public function test_invalid_signature_is_rejected_and_no_order_is_created(): void
    {
        $channel = $this->channel();
        $payload = $this->webhookOrder(4002);
        $body = json_encode($payload);

        $response = $this->call('POST', '/api/webhooks/woocommerce/' . $channel->id, [], [], [], [
            'HTTP_X-WC-Webhook-Signature' => 'not-a-valid-signature',
            'HTTP_X-WC-Webhook-Topic' => 'order.updated',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertStatus(401);
        self::assertSame(0, CommerceOrder::where('sales_channel_id', $channel->id)->count());
    }

    public function test_repeated_delivery_of_the_same_webhook_does_not_duplicate_order(): void
    {
        $channel = $this->channel();
        $payload = $this->webhookOrder(4003);
        $body = json_encode($payload);
        $signature = $this->sign($body, 'test-secret-value');

        foreach (range(1, 3) as $_) {
            $this->call('POST', '/api/webhooks/woocommerce/' . $channel->id, [], [], [], [
                'HTTP_X-WC-Webhook-Signature' => $signature,
                'HTTP_X-WC-Webhook-Topic' => 'order.updated',
                'CONTENT_TYPE' => 'application/json',
            ], $body)->assertOk();
        }

        self::assertSame(1, CommerceOrder::where('sales_channel_id', $channel->id)->count());
    }

    public function test_webhook_cannot_be_used_against_a_different_companys_channel_secret(): void
    {
        $channel = $this->channel();
        $payload = $this->webhookOrder(4004);
        $body = json_encode($payload);

        // Signed with the wrong secret (as if an attacker guessed the channel id but not its secret).
        $response = $this->call('POST', '/api/webhooks/woocommerce/' . $channel->id, [], [], [], [
            'HTTP_X-WC-Webhook-Signature' => $this->sign($body, 'someone-elses-secret'),
            'HTTP_X-WC-Webhook-Topic' => 'order.updated',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertStatus(401);
        self::assertSame(0, CommerceOrder::count());
    }
}

<?php

namespace App\Services\Integrations\WooCommerce;

use App\Models\SalesChannel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class WooCommerceClient
{
    private SalesChannel $channel;

    public function __construct(SalesChannel $channel)
    {
        $this->channel = $channel;
    }

    public function testConnection(): bool
    {
        $response = $this->request()->get($this->url('/wp-json/wc/v3/system_status'));
        return $response->successful();
    }

    public function getOrders(array $params = []): array
    {
        $default = [
            'per_page' => 50,
            'page' => 1,
            'orderby' => 'date',
            'order' => 'desc',
        ];

        return $this->request()
            ->get($this->url('/wp-json/wc/v3/orders'), array_merge($default, $params))
            ->throw()
            ->json() ?? [];
    }

    public function updateOrderStatus($externalOrderId, string $status): array
    {
        return $this->request()
            ->put($this->url('/wp-json/wc/v3/orders/' . $externalOrderId), [
                'status' => $status,
            ])
            ->throw()
            ->json() ?? [];
    }

    public function addOrderNote($externalOrderId, string $note, bool $customerNote = false): array
    {
        return $this->request()
            ->post($this->url('/wp-json/wc/v3/orders/' . $externalOrderId . '/notes'), [
                'note' => $note,
                'customer_note' => $customerNote,
            ])
            ->throw()
            ->json() ?? [];
    }

    private function request(): PendingRequest
    {
        $credentials = $this->credentials();

        return Http::timeout(30)
            ->retry(2, 1000)
            ->withBasicAuth($credentials['consumer_key'] ?? '', $credentials['consumer_secret'] ?? '')
            ->acceptJson();
    }

    private function credentials(): array
    {
        $raw = $this->channel->credentials_encrypted ?? $this->channel->credentials ?? null;
        if (!$raw) {
            return [];
        }

        try {
            $decoded = Crypt::decryptString($raw);
            $json = json_decode($decoded, true);
            return is_array($json) ? $json : [];
        } catch (\Throwable $e) {
            $json = json_decode($raw, true);
            return is_array($json) ? $json : [];
        }
    }

    private function url(string $path): string
    {
        $base = $this->channel->base_url ?: $this->channel->url ?: '';
        $base = trim($base);

        if ($base !== '' && !preg_match('#^https?://#i', $base)) {
            $base = 'https://' . $base;
        }

        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

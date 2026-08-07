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
        return $this->testConnectionDetails()['ok'];
    }

    public function testConnectionDetails(): array
    {
        $base = trim((string) ($this->channel->base_url ?? ''));
        $parsed = filter_var($base, FILTER_VALIDATE_URL);
        if (! $parsed || ! str_starts_with(strtolower($base), 'https://')) {
            return ['ok' => false, 'message' => 'URL sklepu musi być poprawnym adresem HTTPS.'];
        }

        try {
            $response = $this->request()->get($this->url('/wp-json/wc/v3/orders'), ['per_page' => 1]);
            if ($response->status() === 401 || $response->status() === 403) return ['ok' => false, 'message' => 'WooCommerce odrzucił klucz API. Sprawdź uprawnienia Read/Write oraz WAF.'];
            if ($response->status() === 429) return ['ok' => false, 'message' => 'WooCommerce ograniczył liczbę żądań. Spróbuj ponownie później.'];
            if (! $response->successful()) return ['ok' => false, 'message' => 'REST API WooCommerce zwróciło błąd HTTP ' . $response->status() . '.'];
            return ['ok' => true, 'message' => 'Połączenie z WooCommerce REST API działa.'];
        } catch (\Throwable $e) {
            report($e);
            return ['ok' => false, 'message' => 'Nie można połączyć się z WooCommerce. Sprawdź HTTPS, URL i blokady WAF.'];
        }
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

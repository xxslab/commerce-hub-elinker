<?php

namespace App\Services\Integrations\Allegro;

use App\Models\SalesChannel;
use Illuminate\Support\Facades\Http;

class AllegroClient
{
    public function __construct(private SalesChannel $channel) {}

    public function getAuthorizationUrl(string $state): string
    {
        return config('commerce-hub.allegro.auth_url') . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => config('commerce-hub.allegro.client_id'),
            'redirect_uri' => config('commerce-hub.allegro.redirect_uri'),
            'state' => $state,
        ]);
    }

    public function getOrders(?string $from = null, int $offset = 0, int $limit = 100): array
    {
        $query = [];
        if ($from) {
            $query['updatedAt.gte'] = $from;
        }
        $query['offset'] = $offset;
        $query['limit'] = min($limit, 100);

        $response = $this->request()->get($this->apiUrl('/order/checkout-forms'), $query);
        $response->throw();

        return $response->json('checkoutForms', []);
    }

    public function addShipment(string $checkoutFormId, string $carrierId, string $trackingNumber): array
    {
        $response = $this->request()->post($this->apiUrl("/order/checkout-forms/{$checkoutFormId}/shipments"), [
            'carrierId' => $carrierId,
            'waybill' => $trackingNumber,
        ]);
        $response->throw();

        return $response->json();
    }

    public function updateFulfillmentStatus(string $checkoutFormId, string $status): array
    {
        $response = $this->request()->put($this->apiUrl("/order/checkout-forms/{$checkoutFormId}/fulfillment"), [
            'status' => $status,
        ]);
        $response->throw();

        return $response->json();
    }

    private function request()
    {
        $credentials = $this->channel->getCredentials();

        return Http::timeout(30)
            ->retry(3, 500)
            ->withToken($credentials['access_token'] ?? '')
            ->withHeaders([
                'Accept' => 'application/vnd.allegro.public.v1+json',
                'Content-Type' => 'application/vnd.allegro.public.v1+json',
            ]);
    }

    private function apiUrl(string $path): string
    {
        return rtrim(config('commerce-hub.allegro.api_url'), '/') . $path;
    }
}

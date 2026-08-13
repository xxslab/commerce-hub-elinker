<?php

namespace App\Services\Integrations\Ebay;

use App\Models\SalesChannel;
use Illuminate\Support\Facades\Http;

class EbayClient
{
    public function __construct(private SalesChannel $channel) {}

    public function getAuthorizationUrl(string $state, array $scopes = []): string
    {
        $scopes = $scopes ?: [
            'https://api.ebay.com/oauth/api_scope/sell.fulfillment.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
        ];

        return config('commerce-hub.ebay.auth_url') . '?' . http_build_query([
            'client_id' => config('commerce-hub.ebay.client_id'),
            'redirect_uri' => config('commerce-hub.ebay.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
        ]);
    }

    public function getOrders(?string $from = null, int $offset = 0, int $limit = 100): array
    {
        $query = [];
        if ($from) {
            $query['filter'] = 'lastmodifieddate:[' . $from . '..]';
        }
        $query['offset'] = $offset;
        $query['limit'] = min($limit, 200);

        $response = $this->request()->get($this->apiUrl('/sell/fulfillment/v1/order'), $query);
        $response->throw();

        return $response->json('orders', []);
    }

    public function createShippingFulfillment(string $orderId, string $carrierCode, string $trackingNumber): array
    {
        $response = $this->request()->post($this->apiUrl("/sell/fulfillment/v1/order/{$orderId}/shipping_fulfillment"), [
            'lineItems' => [],
            'shippedDate' => now()->toIso8601String(),
            'shippingCarrierCode' => $carrierCode,
            'trackingNumber' => $trackingNumber,
        ]);
        $response->throw();

        return $response->json();
    }

    private function request()
    {
        $credentials = $this->channel->getCredentials();

        return Http::timeout(30)
            ->retry(3, 500, function (\Throwable $exception) {
                if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                    return ! in_array($exception->response->status(), [400, 401, 403, 404, 422], true);
                }

                return true;
            })
            ->withToken($credentials['access_token'] ?? '')
            ->acceptJson();
    }

    private function apiUrl(string $path): string
    {
        return rtrim(config('commerce-hub.ebay.api_url'), '/') . $path;
    }
}

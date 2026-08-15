<?php

namespace App\Services\Integrations\Ebay;

use App\Models\CommerceOrder;
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

    /**
     * lineItems must reference eBay's own lineItemId (not our internal id, and
     * not the legacyItemId some of our records were seeded with) — see
     * EbayOrderSyncService for where that's captured in raw_payload.
     * shippingCarrierCode must be one of eBay's ShippingCarrierCodeType enum
     * values for the order's marketplace; that enum is marketplace-specific
     * and only resolvable via eBay's (separate, Trading API) GeteBayDetails
     * call, so it's config-driven here with the documented generic "Other"
     * fallback rather than a guessed literal.
     */
    public function createShippingFulfillment(CommerceOrder $order, string $carrierName, string $trackingNumber): array
    {
        $lineItems = $order->items->map(function ($item) {
            $lineItemId = $item->raw_payload['lineItemId'] ?? $item->external_product_id;

            return array_filter([
                'lineItemId' => $lineItemId ? (string) $lineItemId : null,
                'quantity' => (int) $item->quantity ?: 1,
            ]);
        })->filter(fn ($item) => !empty($item['lineItemId']))->values()->all();

        $response = $this->request()->post($this->apiUrl("/sell/fulfillment/v1/order/{$order->external_order_id}/shipping_fulfillment"), [
            'lineItems' => $lineItems,
            'shippedDate' => now()->toIso8601String(),
            'shippingCarrierCode' => $this->carrierCode($carrierName),
            'trackingNumber' => $trackingNumber,
        ]);
        $response->throw();

        return $response->json();
    }

    private function carrierCode(string $carrierName): string
    {
        $map = config('commerce-hub.ebay.carrier_codes', []);

        return $map[strtolower($carrierName)] ?? 'Other';
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

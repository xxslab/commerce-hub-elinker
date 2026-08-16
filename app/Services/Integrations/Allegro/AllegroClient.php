<?php

namespace App\Services\Integrations\Allegro;

use App\Models\SalesChannel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AllegroClient
{
    public function __construct(private SalesChannel $channel) {}

    /**
     * GET /order/carriers — the list of carrier identifiers Allegro accepts in
     * addShipment(). Cached per channel since it rarely changes and this is
     * called on every tracking push.
     */
    public function getCarriers(): array
    {
        return Cache::remember('allegro-carriers:' . $this->channel->id, now()->addDay(), function () {
            $response = $this->request()->get($this->apiUrl('/order/carriers'));
            $response->throw();

            return $response->json('carriers', []);
        });
    }

    /**
     * Resolves a human carrier name (e.g. "InPost") to the carrierId Allegro
     * expects. Falls back to the documented carrierId=OTHER + carrierName
     * pair when the name isn't found in the live carrier list, since that
     * combination is guaranteed valid regardless of what Allegro's current
     * carrier catalogue contains.
     */
    public function resolveCarrier(string $name): array
    {
        try {
            foreach ($this->getCarriers() as $carrier) {
                $carrierName = (string) ($carrier['name'] ?? $carrier['carrierName'] ?? '');
                if ($carrierName !== '' && str_contains(strtolower($carrierName), strtolower($name))) {
                    return ['carrierId' => (string) $carrier['id']];
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return ['carrierId' => 'OTHER', 'carrierName' => $name];
    }

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

    /**
     * $carrierName is a human carrier name (e.g. "InPost"), not a raw Allegro
     * carrierId — see resolveCarrier().
     */
    public function addShipment(string $checkoutFormId, string $carrierName, string $trackingNumber): array
    {
        $response = $this->request()->post($this->apiUrl("/order/checkout-forms/{$checkoutFormId}/shipments"), array_merge(
            $this->resolveCarrier($carrierName),
            ['waybill' => $trackingNumber]
        ));
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
            ->retry(3, 500, function (\Throwable $exception) {
                if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                    return ! in_array($exception->response->status(), [400, 401, 403, 404, 422], true);
                }

                return true;
            })
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

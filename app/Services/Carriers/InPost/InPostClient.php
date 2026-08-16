<?php

namespace App\Services\Carriers\InPost;

use App\Models\CommerceOrder;
use App\Models\Shipment;
use App\Models\ShippingLabel;
use App\Models\ShipmentEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class InPostClient
{
    /**
     * Idempotent: returns the existing active InPost shipment for this order
     * instead of creating a duplicate if one was already created (e.g. double click).
     */
    public function createShipment(CommerceOrder $order, array $parcel = []): Shipment
    {
        $lock = Cache::lock('inpost-create-shipment:' . $order->id, 30);

        return $lock->block(10, function () use ($order, $parcel) {
            $existing = Shipment::where('commerce_order_id', $order->id)
                ->where('carrier', 'inpost')
                ->whereNotIn('status', ['CANCELLED', 'ERROR'])
                ->first();

            if ($existing) {
                return $existing;
            }

            $payload = $this->buildShipmentPayload($order, $parcel);

            try {
                $response = $this->request()->post($this->apiUrl('/v1/organizations/' . config('commerce-hub.inpost.organization_id') . '/shipments'), $payload);
                $response->throw();
            } catch (\Illuminate\Http\Client\RequestException $e) {
                throw $this->actionableException($e);
            }
            $data = $response->json();

            return Shipment::create([
                'company_id' => $order->company_id,
                'commerce_order_id' => $order->id,
                'carrier' => 'inpost',
                'external_shipment_id' => (string) ($data['id'] ?? ''),
                'tracking_number' => $data['tracking_number'] ?? null,
                'status' => strtoupper($data['status'] ?? 'CREATED'),
                'request_payload' => $payload,
                'raw_payload' => $data,
            ]);
        });
    }

    public function downloadLabel(Shipment $shipment, string $format = 'pdf'): Shipment
    {
        try {
            $response = $this->request()->get($this->apiUrl('/v1/shipments/' . $shipment->external_shipment_id . '/label'), [
                'format' => $format,
            ]);
            $response->throw();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            throw $this->actionableException($e);
        }

        $path = 'labels/inpost/' . $shipment->id . '.' . $format;
        Storage::put($path, $response->body());

        $shipment->forceFill([
            'label_format' => $format,
            'label_path' => $path,
            'status' => 'LABEL_PRINTED',
        ])->save();
        ShippingLabel::updateOrCreate(['shipment_id' => $shipment->id, 'format' => $format], ['path' => $path]);

        return $shipment;
    }

    public function refreshTracking(Shipment $shipment): Shipment
    {
        if (!$shipment->tracking_number) {
            return $shipment;
        }

        try {
            $response = $this->request()->get($this->apiUrl('/v1/tracking/' . $shipment->tracking_number));
            $response->throw();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            throw $this->actionableException($e);
        }
        $data = $response->json();

        $shipment->forceFill([
            'status' => strtoupper($data['status'] ?? $shipment->status),
            'last_tracking_sync_at' => now(),
            'raw_payload' => array_merge($shipment->raw_payload ?? [], ['tracking' => $data]),
        ])->save();
        ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'status' => $shipment->status,
            'occurred_at' => now(),
            'raw_payload' => $data,
        ]);

        return $shipment;
    }

    private function actionableException(\Illuminate\Http\Client\RequestException $e): \RuntimeException
    {
        $status = $e->response->status();

        $message = match (true) {
            in_array($status, [401, 403], true) => 'Token InPost jest nieprawidłowy lub wygasł. Sprawdź konfigurację (INPOST_API_TOKEN).',
            $status === 422 => 'InPost odrzucił dane przesyłki (błędny adres, kod Paczkomatu lub parametry paczki). Sprawdź dane odbiorcy i spróbuj ponownie.',
            $status === 429 => 'InPost ograniczył liczbę żądań (rate limit). Spróbuj ponownie za chwilę.',
            $status >= 500 => 'InPost API jest chwilowo niedostępne (błąd serwera). Spróbuj ponownie później.',
            default => 'InPost API zwróciło błąd HTTP ' . $status . '.',
        };

        return new \RuntimeException($message, 0, $e);
    }

    private function buildShipmentPayload(CommerceOrder $order, array $parcel): array
    {
        $address = $order->shipping_address ?? [];
        $point = trim((string) ($parcel['point'] ?? ''));
        $isLocker = $point !== '';

        $receiver = [
            'name' => $order->customer_name,
            'email' => $order->customer_email,
            'phone' => $order->customer_phone,
        ];

        if (! $isLocker) {
            $receiver['address'] = [
                'street' => trim(($address['address_1'] ?? '') . ' ' . ($address['address_2'] ?? '')),
                'building_number' => $address['building_number'] ?? '',
                'city' => $address['city'] ?? '',
                'post_code' => $address['postcode'] ?? '',
                'country_code' => $address['country'] ?? 'PL',
            ];
        }

        $payload = [
            'receiver' => $receiver,
            'parcels' => [[
                'template' => $parcel['template'] ?? 'small',
                'weight' => ['amount' => $parcel['weight'] ?? 1, 'unit' => 'kg'],
            ]],
            'service' => $parcel['service'] ?? ($isLocker ? 'inpost_locker_standard' : 'inpost_courier_standard'),
            'reference' => $order->order_number,
            'comments' => 'Commerce Hub order #' . $order->order_number,
        ];

        if ($isLocker) {
            $payload['custom_attributes'] = ['target_point' => $point];
        }

        return $payload;
    }

    private function request()
    {
        return Http::timeout(30)
            ->retry(3, 500, function (\Throwable $exception) {
                if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                    return ! in_array($exception->response->status(), [400, 401, 403, 404, 422], true);
                }

                return true;
            })
            ->withToken(config('commerce-hub.inpost.token'))
            ->acceptJson();
    }

    private function apiUrl(string $path): string
    {
        return rtrim(config('commerce-hub.inpost.api_url'), '/') . $path;
    }
}

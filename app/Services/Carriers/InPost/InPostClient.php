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

            $response = $this->request()->post($this->apiUrl('/v1/organizations/' . config('commerce-hub.inpost.organization_id') . '/shipments'), $payload);
            $response->throw();
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
        $response = $this->request()->get($this->apiUrl('/v1/shipments/' . $shipment->external_shipment_id . '/label'), [
            'format' => $format,
        ]);
        $response->throw();

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

        $response = $this->request()->get($this->apiUrl('/v1/tracking/' . $shipment->tracking_number));
        $response->throw();
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
            ->retry(3, 500)
            ->withToken(config('commerce-hub.inpost.token'))
            ->acceptJson();
    }

    private function apiUrl(string $path): string
    {
        return rtrim(config('commerce-hub.inpost.api_url'), '/') . $path;
    }
}

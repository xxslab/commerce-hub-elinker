<?php

namespace App\Services\Integrations\Allegro;

use App\Models\CommerceOrder;
use App\Models\OrderItem;
use App\Models\SalesChannel;
use App\Services\Orders\OrderStatusMapper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AllegroOrderSyncService
{
    public function sync(SalesChannel $channel): int
    {
        $client = new AllegroClient($channel);
        $orders = [];
        $offset = 0;
        do {
            $page = $client->getOrders($channel->last_orders_sync_at?->toIso8601String(), $offset, 100);
            $orders = array_merge($orders, $page);
            $offset += count($page);
        } while (count($page) === 100 && $offset < 10000);

        foreach ($orders as $payload) $this->upsertOrder($channel, $payload);

        $channel->forceFill(['last_orders_sync_at' => now()])->save();

        return count($orders);
    }

    private function upsertOrder(SalesChannel $channel, array $payload): void
    {
        DB::transaction(function () use ($channel, $payload) {
            $buyer = $payload['buyer'] ?? [];
            $delivery = $payload['delivery'] ?? [];
            $payment = $payload['payment'] ?? [];
            $status = $payload['status'] ?? null;
            $paymentStatus = $payment['status'] ?? null;
            $shippingStatus = $delivery['status'] ?? null;

            $order = CommerceOrder::updateOrCreate(
                [
                    'sales_channel_id' => $channel->id,
                    'external_order_id' => (string) $payload['id'],
                ],
                [
                    'company_id' => $channel->company_id,
                    'source' => 'allegro',
                    'order_number' => (string) ($payload['id'] ?? null),
                    'status_source' => $status,
                    'status_normalized' => OrderStatusMapper::normalize('allegro', $status, $paymentStatus, $shippingStatus),
                    'payment_status' => $paymentStatus,
                    'shipping_status' => $shippingStatus,
                    'currency' => $payload['summary']['totalToPay']['currency'] ?? 'PLN',
                    'total' => (float) ($payload['summary']['totalToPay']['amount'] ?? 0),
                    'customer_name' => $buyer['login'] ?? null,
                    'customer_email' => $buyer['email'] ?? null,
                    'customer_phone' => $delivery['address']['phoneNumber'] ?? null,
                    'billing_country' => null,
                    'shipping_country' => $delivery['address']['countryCode'] ?? null,
                    'shipping_address' => $delivery['address'] ?? null,
                    'ordered_at' => isset($payload['boughtAt']) ? Carbon::parse($payload['boughtAt']) : null,
                    'source_updated_at' => isset($payload['updatedAt']) ? Carbon::parse($payload['updatedAt']) : null,
                    'raw_payload' => $payload,
                ]
            );

            $order->items()->delete();
            foreach (($payload['lineItems'] ?? []) as $item) {
                OrderItem::create([
                    'commerce_order_id' => $order->id,
                    'external_product_id' => $item['offer']['id'] ?? null,
                    'sku' => $item['offer']['external']['id'] ?? null,
                    'name' => $item['offer']['name'] ?? 'Allegro item',
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'price' => (float) ($item['price']['amount'] ?? 0),
                    'tax' => 0,
                    'total' => (float) (($item['price']['amount'] ?? 0) * ($item['quantity'] ?? 1)),
                    'raw_payload' => $item,
                ]);
            }
        });
    }
}

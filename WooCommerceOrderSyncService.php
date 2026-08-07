<?php

namespace App\Services\Integrations\WooCommerce;

use App\Models\CommerceOrder;
use App\Models\OrderItem;
use App\Models\SalesChannel;
use App\Services\Orders\OrderStatusMapper;
use Illuminate\Support\Facades\DB;

class WooCommerceOrderSyncService
{
    public function sync(SalesChannel $channel): int
    {
        $client = new WooCommerceClient($channel);
        $after = $channel->last_orders_sync_at
            ? $channel->last_orders_sync_at->toIso8601String()
            : now()->subDays(30)->toIso8601String();

        $page = 1;
        $count = 0;

        do {
            $orders = $client->getOrders([
                'after' => $after,
                'page' => $page,
            ]);

            foreach ($orders as $payload) {
                $this->upsertOrder($channel, $payload);
                $count++;
            }

            $page++;
        } while (count($orders) === 50 && $page <= 50);

        $channel->forceFill([
            'last_orders_sync_at' => now(),
            'last_sync_at' => now(),
            'last_sync_count' => $count,
            'sync_status' => 'idle',
            'last_error' => null,
        ])->save();

        return $count;
    }

    private function upsertOrder(SalesChannel $channel, array $payload): void
    {
        DB::transaction(function () use ($channel, $payload) {
            $billing = $payload['billing'] ?? [];
            $shipping = $payload['shipping'] ?? [];
            $externalId = (string)($payload['id'] ?? $payload['number'] ?? '');

            $order = CommerceOrder::updateOrCreate(
                [
                    'sales_channel_id' => $channel->id,
                    'external_order_id' => $externalId,
                ],
                [
                    'company_id' => $channel->company_id,
                    'source' => 'woocommerce',
                    'order_number' => (string)($payload['number'] ?? $externalId),
                    'status_source' => (string)($payload['status'] ?? ''),
                    'status_normalized' => OrderStatusMapper::mapWoo((string)($payload['status'] ?? '')),
                    'currency' => (string)($payload['currency'] ?? ''),
                    'total' => (float)($payload['total'] ?? 0),
                    'payment_status' => !empty($payload['date_paid']) ? 'paid' : 'unknown',
                    'shipping_status' => null,
                    'customer_name' => trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? '')),
                    'customer_email' => $billing['email'] ?? null,
                    'customer_phone' => $billing['phone'] ?? null,
                    'billing_country' => $billing['country'] ?? null,
                    'shipping_country' => $shipping['country'] ?? null,
                    'ordered_at' => $payload['date_created'] ?? now(),
                    'raw_payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]
            );

            OrderItem::where('commerce_order_id', $order->id)->delete();

            foreach (($payload['line_items'] ?? []) as $item) {
                OrderItem::create([
                    'commerce_order_id' => $order->id,
                    'external_product_id' => (string)($item['product_id'] ?? ''),
                    'sku' => $item['sku'] ?? null,
                    'name' => $item['name'] ?? '',
                    'quantity' => (int)($item['quantity'] ?? 0),
                    'price' => (float)($item['price'] ?? 0),
                    'tax' => (float)($item['total_tax'] ?? 0),
                    'total' => (float)($item['total'] ?? 0),
                ]);
            }
        });
    }
}

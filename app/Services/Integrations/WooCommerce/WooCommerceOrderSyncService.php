<?php

namespace App\Services\Integrations\WooCommerce;

use App\Models\CommerceOrder;
use App\Models\OrderItem;
use App\Models\SalesChannel;
use App\Services\Orders\OrderStatusMapper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WooCommerceOrderSyncService
{
    public function sync(SalesChannel $channel): array
    {
        $client = new WooCommerceClient($channel);
        $after = $channel->last_orders_sync_at
            ? $channel->last_orders_sync_at->toIso8601String()
            : now()->subDays(30)->toIso8601String();
        $page = 1;
        $stats = ['fetched' => 0, 'created' => 0, 'updated' => 0];

        do {
            $orders = $client->getOrders(['after' => $after, 'page' => $page]);
            foreach ($orders as $payload) {
                $stats['fetched']++;
                $order = $this->upsertOrder($channel, $payload);
                $stats[$order->wasRecentlyCreated ? 'created' : 'updated']++;
            }
            $page++;
        } while (count($orders) === 50 && $page <= 50);

        $channel->forceFill([
            'last_orders_sync_at' => now(),
            'last_sync_at' => now(),
            'last_sync_count' => $stats['fetched'],
            'sync_status' => 'idle',
            'last_error' => null,
        ])->save();

        return $stats;
    }

    /**
     * Apply a single webhook payload. Idempotent via the same
     * (sales_channel_id, external_order_id) upsert key used by polling sync,
     * with a guard against an out-of-order (stale) webhook delivery overwriting
     * newer data already stored locally.
     */
    public function upsertFromWebhook(SalesChannel $channel, array $payload): CommerceOrder
    {
        $externalId = (string) ($payload['id'] ?? $payload['number'] ?? '');

        $existing = CommerceOrder::where('sales_channel_id', $channel->id)
            ->where('external_order_id', $externalId)
            ->first();

        if ($existing && $existing->source_updated_at && !empty($payload['date_modified'])) {
            if (Carbon::parse($payload['date_modified'])->lt($existing->source_updated_at)) {
                return $existing;
            }
        }

        return $this->upsertOrder($channel, $payload);
    }

    public function upsertOrder(SalesChannel $channel, array $payload): CommerceOrder
    {
        return DB::transaction(function () use ($channel, $payload) {
            $billing = $payload['billing'] ?? [];
            $shipping = $payload['shipping'] ?? [];
            $externalId = (string) ($payload['id'] ?? $payload['number'] ?? '');
            $order = CommerceOrder::updateOrCreate(
                ['sales_channel_id' => $channel->id, 'external_order_id' => $externalId],
                [
                    'company_id' => $channel->company_id,
                    'source' => 'woocommerce',
                    'order_number' => (string) ($payload['number'] ?? $externalId),
                    'external_order_number' => (string) ($payload['number'] ?? $externalId),
                    'status_source' => (string) ($payload['status'] ?? ''),
                    'status_normalized' => OrderStatusMapper::mapWoo((string) ($payload['status'] ?? '')),
                    'currency' => (string) ($payload['currency'] ?? ''),
                    'total' => (float) ($payload['total'] ?? 0),
                    'products_total' => (float) ($payload['total'] ?? 0) - (float) ($payload['shipping_total'] ?? 0),
                    'shipping_total' => (float) ($payload['shipping_total'] ?? 0),
                    'discount_total' => (float) ($payload['discount_total'] ?? 0),
                    'tax_total' => (float) ($payload['total_tax'] ?? 0),
                    'payment_status' => !empty($payload['date_paid']) ? 'paid' : 'unknown',
                    'customer_name' => trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? '')),
                    'customer_email' => $billing['email'] ?? null,
                    'customer_phone' => $billing['phone'] ?? null,
                    'billing_country' => $billing['country'] ?? null,
                    'shipping_country' => $shipping['country'] ?? null,
                    'billing_address' => $billing,
                    'shipping_address' => $shipping,
                    'payment_method' => $payload['payment_method_title'] ?? ($payload['payment_method'] ?? null),
                    'shipping_method' => $payload['shipping_lines'][0]['method_title'] ?? null,
                    'customer_note' => $payload['customer_note'] ?? null,
                    'ordered_at' => $payload['date_created'] ?? now(),
                    'source_updated_at' => $payload['date_modified'] ?? null,
                    'raw_payload' => $payload,
                    'last_synced_at' => now(),
                ]
            );

            OrderItem::where('commerce_order_id', $order->id)->delete();
            foreach (($payload['line_items'] ?? []) as $item) {
                OrderItem::create([
                    'commerce_order_id' => $order->id,
                    'external_product_id' => (string) ($item['product_id'] ?? ''),
                    'sku' => $item['sku'] ?? null,
                    'name' => $item['name'] ?? '',
                    'quantity' => (int) ($item['quantity'] ?? 0),
                    'price' => (float) ($item['price'] ?? 0),
                    'tax' => (float) ($item['total_tax'] ?? 0),
                    'total' => (float) ($item['total'] ?? 0),
                    'variant' => $item['variation_id'] ?? null,
                    'raw_payload' => $item,
                ]);
            }

            return $order;
        });
    }
}

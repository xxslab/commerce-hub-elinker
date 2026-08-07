<?php

namespace App\Services\Integrations\Ebay;

use App\Models\CommerceOrder;
use App\Models\OrderItem;
use App\Models\SalesChannel;
use App\Services\Orders\OrderStatusMapper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EbayOrderSyncService
{
    public function sync(SalesChannel $channel): int
    {
        $client = new EbayClient($channel);
        $orders = [];
        $offset = 0;
        do {
            $page = $client->getOrders($channel->last_orders_sync_at?->toIso8601String(), $offset, 200);
            $orders = array_merge($orders, $page);
            $offset += count($page);
        } while (count($page) === 200 && $offset < 10000);

        foreach ($orders as $payload) $this->upsertOrder($channel, $payload);

        $channel->forceFill(['last_orders_sync_at' => now()])->save();

        return count($orders);
    }

    private function upsertOrder(SalesChannel $channel, array $payload): void
    {
        DB::transaction(function () use ($channel, $payload) {
            $buyer = $payload['buyer'] ?? [];
            $pricing = $payload['pricingSummary']['total'] ?? [];
            $fulfillmentStatus = $payload['orderFulfillmentStatus'] ?? null;
            $paymentStatus = $payload['orderPaymentStatus'] ?? null;

            $order = CommerceOrder::updateOrCreate(
                [
                    'sales_channel_id' => $channel->id,
                    'external_order_id' => (string) $payload['orderId'],
                ],
                [
                    'company_id' => $channel->company_id,
                    'source' => 'ebay',
                    'order_number' => (string) ($payload['legacyOrderId'] ?? $payload['orderId']),
                    'status_source' => $fulfillmentStatus,
                    'status_normalized' => OrderStatusMapper::normalize('ebay', $fulfillmentStatus, $paymentStatus, $fulfillmentStatus),
                    'payment_status' => $paymentStatus,
                    'shipping_status' => $fulfillmentStatus,
                    'currency' => $pricing['currency'] ?? null,
                    'total' => (float) ($pricing['value'] ?? 0),
                    'customer_name' => $buyer['username'] ?? null,
                    'customer_email' => $buyer['email'] ?? null,
                    'ordered_at' => isset($payload['creationDate']) ? Carbon::parse($payload['creationDate']) : null,
                    'source_updated_at' => isset($payload['lastModifiedDate']) ? Carbon::parse($payload['lastModifiedDate']) : null,
                    'raw_payload' => $payload,
                ]
            );

            $order->items()->delete();
            foreach (($payload['lineItems'] ?? []) as $item) {
                OrderItem::create([
                    'commerce_order_id' => $order->id,
                    'external_product_id' => $item['legacyItemId'] ?? $item['lineItemId'] ?? null,
                    'sku' => $item['sku'] ?? null,
                    'name' => $item['title'] ?? 'eBay item',
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'price' => (float) ($item['lineItemCost']['value'] ?? 0),
                    'tax' => 0,
                    'total' => (float) ($item['total']['value'] ?? $item['lineItemCost']['value'] ?? 0),
                    'raw_payload' => $item,
                ]);
            }
        });
    }
}

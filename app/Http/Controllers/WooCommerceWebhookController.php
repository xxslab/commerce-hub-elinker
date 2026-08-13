<?php

namespace App\Http\Controllers;

use App\Models\SalesChannel;
use App\Models\SyncLog;
use App\Services\Integrations\WooCommerce\WooCommerceOrderSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WooCommerceWebhookController extends Controller
{
    /**
     * Receives WooCommerce order webhooks. Unauthenticated by necessity (WooCommerce
     * cannot present a Sanctum token) but verified via the per-channel HMAC secret
     * configured on both sides, matching WooCommerce's own webhook signing scheme.
     */
    public function handle(Request $request, SalesChannel $salesChannel, WooCommerceOrderSyncService $service)
    {
        if ($salesChannel->type !== SalesChannel::TYPE_WOOCOMMERCE) {
            abort(404);
        }

        $secret = $salesChannel->getWebhookSecret();
        if (!$secret) {
            abort(404);
        }

        $signature = $request->header('X-WC-Webhook-Signature', '');
        $computed = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        if ($signature === '' || !hash_equals($computed, $signature)) {
            SyncLog::create([
                'sales_channel_id' => $salesChannel->id,
                'type' => 'webhook',
                'status' => 'failed',
                'message' => 'Nieprawidłowy podpis webhooka WooCommerce.',
                'started_at' => now(),
                'finished_at' => now(),
            ]);

            return response()->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        $topic = (string) $request->header('X-WC-Webhook-Topic', '');
        $deliveryId = $request->header('X-WC-Webhook-Delivery-ID');
        $payload = (array) $request->json()->all();

        // WooCommerce sends a payload-less ping when the webhook is first created/activated.
        if (empty($payload['id']) && empty($payload['line_items'])) {
            return response()->json(['ok' => true, 'message' => 'ping']);
        }

        if (str_contains($topic, 'deleted')) {
            SyncLog::create([
                'sales_channel_id' => $salesChannel->id,
                'type' => 'webhook',
                'status' => 'success',
                'message' => 'Zamówienie usunięte w WooCommerce (zachowane lokalnie): ' . ($payload['id'] ?? 'n/a'),
                'context' => ['topic' => $topic, 'delivery_id' => $deliveryId],
                'started_at' => now(),
                'finished_at' => now(),
            ]);

            return response()->json(['ok' => true]);
        }

        try {
            $order = $service->upsertFromWebhook($salesChannel, $payload);

            SyncLog::create([
                'sales_channel_id' => $salesChannel->id,
                'type' => 'webhook',
                'status' => 'success',
                'message' => 'Webhook zastosowany dla zamówienia ' . $order->order_number,
                'records_count' => 1,
                'context' => ['topic' => $topic, 'delivery_id' => $deliveryId, 'commerce_order_id' => $order->id],
                'started_at' => now(),
                'finished_at' => now(),
            ]);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            report($e);

            SyncLog::create([
                'sales_channel_id' => $salesChannel->id,
                'type' => 'webhook',
                'status' => 'failed',
                'message' => 'Przetwarzanie webhooka nie powiodło się. Szczegóły w logach aplikacji.',
                'context' => ['topic' => $topic, 'delivery_id' => $deliveryId],
                'started_at' => now(),
                'finished_at' => now(),
            ]);

            return response()->json(['ok' => false], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

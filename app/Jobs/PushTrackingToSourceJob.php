<?php

namespace App\Jobs;

use App\Models\Shipment;
use App\Models\SalesChannel;
use App\Services\Integrations\Allegro\AllegroClient;
use App\Services\Integrations\Ebay\EbayClient;
use App\Services\Integrations\WooCommerce\WooCommerceClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PushTrackingToSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $shipmentId) {}

    public $tries = 3;
    public $backoff = [60, 300, 900];

    public function handle(): void
    {
        $shipment = Shipment::with('order.salesChannel')->findOrFail($this->shipmentId);
        $order = $shipment->order;
        $channel = $order->salesChannel;

        if (!$shipment->tracking_number) {
            return;
        }

        match ($channel->type) {
            SalesChannel::TYPE_WOOCOMMERCE => app(WooCommerceClient::class, ['channel' => $channel])
                ->addOrderNote($order->external_order_id, 'Numer przesyłki: ' . $shipment->tracking_number),

            SalesChannel::TYPE_ALLEGRO => app(AllegroClient::class, ['channel' => $channel])
                ->addShipment($order->external_order_id, 'INPOST', $shipment->tracking_number),

            SalesChannel::TYPE_EBAY => app(EbayClient::class, ['channel' => $channel])
                ->createShippingFulfillment($order->external_order_id, 'INPOST', $shipment->tracking_number),

            default => null,
        };
    }
}

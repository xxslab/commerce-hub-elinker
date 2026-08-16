<?php

namespace App\Services\Integrations\Allegro;

use App\Contracts\SalesChannelConnectorInterface;
use App\Models\SalesChannel;

class AllegroConnector implements SalesChannelConnectorInterface
{
    public function __construct(private SalesChannel $channel) {}
    public function testConnection(): array { $orders = (new AllegroClient($this->channel))->getOrders(); return ['ok' => true, 'sample_count' => count($orders)]; }
    public function import(): array { return app(AllegroOrderSyncService::class)->sync($this->channel); }
    public function update(string $externalOrderId, string $status): array
    {
        $allegroStatus = match ($status) {
            'NEW' => 'READY_FOR_PROCESSING',
            'PROCESSING', 'PAID' => 'PROCESSING',
            'READY_TO_SHIP' => 'READY_FOR_SHIPMENT',
            'SHIPPED' => 'SENT',
            'CANCELLED' => 'CANCELLED',
            'COMPLETED' => 'COMPLETED',
            default => throw new \InvalidArgumentException('Status nie jest obsługiwany przez Allegro.'),
        };
        return (new AllegroClient($this->channel))->updateFulfillmentStatus($externalOrderId, $allegroStatus);
    }
}

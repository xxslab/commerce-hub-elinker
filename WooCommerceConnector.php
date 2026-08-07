<?php

namespace App\Services\Integrations\WooCommerce;

use App\Contracts\SalesChannelConnectorInterface;
use App\Models\SalesChannel;

class WooCommerceConnector implements SalesChannelConnectorInterface
{
    public function __construct(private SalesChannel $channel) {}

    public function testConnection(): array
    {
        return (new WooCommerceClient($this->channel))->testConnectionDetails();
    }

    public function import(): array
    {
        return app(WooCommerceOrderSyncService::class)->sync($this->channel);
    }

    public function update(string $externalOrderId, string $status): array
    {
        $wooStatus = match ($status) {
            'NEW', 'ON_HOLD' => 'on-hold',
            'PAID', 'PROCESSING', 'READY_TO_SHIP' => 'processing',
            'SHIPPED', 'COMPLETED' => 'completed',
            'CANCELLED' => 'cancelled',
            'REFUNDED' => 'refunded',
            default => throw new \InvalidArgumentException('Status nie jest obsługiwany przez WooCommerce.'),
        };
        return app(WooCommerceClient::class, ['channel' => $this->channel])->updateOrderStatus($externalOrderId, $wooStatus);
    }
}

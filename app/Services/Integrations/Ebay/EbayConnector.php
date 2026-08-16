<?php

namespace App\Services\Integrations\Ebay;

use App\Contracts\SalesChannelConnectorInterface;
use App\Models\SalesChannel;

class EbayConnector implements SalesChannelConnectorInterface
{
    public function __construct(private SalesChannel $channel) {}
    public function testConnection(): array { $orders = (new EbayClient($this->channel))->getOrders(); return ['ok' => true, 'sample_count' => count($orders)]; }
    public function import(): array { return app(EbayOrderSyncService::class)->sync($this->channel); }
    public function update(string $externalOrderId, string $status): array { return ['ok' => false, 'message' => 'eBay status update wymaga fulfillment API.']; }
}

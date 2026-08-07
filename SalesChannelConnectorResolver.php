<?php

namespace App\Services\Integrations;

use App\Contracts\SalesChannelConnectorInterface;
use App\Models\SalesChannel;
use App\Services\Integrations\Allegro\AllegroConnector;
use App\Services\Integrations\Ebay\EbayConnector;
use App\Services\Integrations\WooCommerce\WooCommerceConnector;

class SalesChannelConnectorResolver
{
    public function for(SalesChannel $channel): SalesChannelConnectorInterface
    {
        return match ($channel->type) {
            SalesChannel::TYPE_WOOCOMMERCE => new WooCommerceConnector($channel),
            SalesChannel::TYPE_ALLEGRO => new AllegroConnector($channel),
            SalesChannel::TYPE_EBAY => new EbayConnector($channel),
            default => throw new \InvalidArgumentException('Nieobsługiwany typ kanału: ' . $channel->type),
        };
    }
}

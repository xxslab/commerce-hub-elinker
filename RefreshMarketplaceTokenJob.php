<?php

namespace App\Jobs;

use App\Models\SalesChannel;
use App\Services\Integrations\Allegro\AllegroOAuthService;
use App\Services\Integrations\Ebay\EbayOAuthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshMarketplaceTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $salesChannelId) {}

    public $tries = 3;
    public $backoff = [60, 300, 900];

    public function handle(AllegroOAuthService $allegro, EbayOAuthService $ebay): void
    {
        $channel = SalesChannel::findOrFail($this->salesChannelId);

        if ($channel->type === SalesChannel::TYPE_ALLEGRO) {
            $allegro->refreshToken($channel);
            return;
        }

        if ($channel->type === SalesChannel::TYPE_EBAY) {
            $ebay->refreshToken($channel);
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\RefreshMarketplaceTokenJob;
use App\Models\SalesChannel;
use Illuminate\Console\Command;

class RefreshMarketplaceTokensCommand extends Command
{
    protected $signature = 'commerce-hub:refresh-marketplace-tokens';
    protected $description = 'Refresh Allegro/eBay OAuth tokens before scheduled order sync.';

    public function handle(): int
    {
        $channels = SalesChannel::query()
            ->whereIn('type', [SalesChannel::TYPE_ALLEGRO, SalesChannel::TYPE_EBAY])
            ->where('status', 'active')
            ->get();

        foreach ($channels as $channel) {
            RefreshMarketplaceTokenJob::dispatch($channel->id);
            $this->line("Queued token refresh for {$channel->type}: {$channel->name}");
        }

        return self::SUCCESS;
    }
}

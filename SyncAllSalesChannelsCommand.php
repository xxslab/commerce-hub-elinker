<?php

namespace App\Console\Commands;

use App\Jobs\SyncSalesChannelOrdersJob;
use App\Models\SalesChannel;
use Illuminate\Console\Command;

class SyncAllSalesChannelsCommand extends Command
{
    protected $signature = 'commerce-hub:sync-orders {--force : Queue channels even if currently syncing}';
    protected $description = 'Queue order synchronization jobs for active sales channels.';

    public function handle(): int
    {
        $query = SalesChannel::query()
            ->whereIn('type', [SalesChannel::TYPE_WOOCOMMERCE, SalesChannel::TYPE_ALLEGRO, SalesChannel::TYPE_EBAY])
            ->where('is_enabled', true)
            ->whereNotIn('sync_status', ['authentication_error', 'disabled']);

        if (!$this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('sync_status')
                    ->orWhere('sync_status', '!=', 'syncing')
                    ->orWhere(function ($stale) {
                        $stale->where('sync_status', 'syncing')
                            ->where(function ($started) {
                                $started->whereNull('last_sync_started_at')
                                    ->orWhere('last_sync_started_at', '<', now()->subMinutes(20));
                            });
                    });
            });
        }

        $count = 0;
        foreach ($query->get() as $channel) {
            $channel->forceFill([
                'sync_status' => 'syncing',
                'last_sync_started_at' => now(),
                'last_error' => null,
            ])->save();
            SyncSalesChannelOrdersJob::dispatch($channel->id);
            $count++;
        }

        $this->info('Queued channels: ' . $count);
        return self::SUCCESS;
    }
}

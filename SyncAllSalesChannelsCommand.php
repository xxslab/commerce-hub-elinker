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
        $query = SalesChannel::query()->where('type', 'woocommerce');

        if (!$this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('sync_status')->orWhere('sync_status', '!=', 'syncing');
            });
        }

        $count = 0;
        foreach ($query->get() as $channel) {
            $channel->forceFill(['sync_status' => 'syncing', 'last_error' => null])->save();
            SyncSalesChannelOrdersJob::dispatch($channel->id);
            $count++;
        }

        $this->info('Queued channels: ' . $count);
        return self::SUCCESS;
    }
}

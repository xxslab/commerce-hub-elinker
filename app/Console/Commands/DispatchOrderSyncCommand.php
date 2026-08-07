<?php

namespace App\Console\Commands;

use App\Jobs\SyncSalesChannelOrdersJob;
use App\Models\SalesChannel;
use Illuminate\Console\Command;

class DispatchOrderSyncCommand extends Command
{
    protected $signature = 'commerce-hub:dispatch-sync {--channel=}';
    protected $description = 'Dispatch order sync jobs for active sales channels';

    public function handle(): int
    {
        SalesChannel::query()
            ->where('status', 'active')
            ->when($this->option('channel'), fn ($q, $id) => $q->where('id', $id))
            ->chunkById(100, function ($channels) {
                foreach ($channels as $channel) {
                    SyncSalesChannelOrdersJob::dispatch($channel->id);
                    $this->info('Queued sync for channel #' . $channel->id . ' ' . $channel->name);
                }
            });

        return self::SUCCESS;
    }
}

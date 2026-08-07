<?php

namespace App\Jobs;

use App\Models\SalesChannel;
use App\Services\Integrations\WooCommerce\WooCommerceOrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncSalesChannelOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public int $salesChannelId;

    public function __construct(int $salesChannelId)
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function handle(): void
    {
        $channel = SalesChannel::findOrFail($this->salesChannelId);

        $channel->forceFill([
            'sync_status' => 'syncing',
            'last_error' => null,
        ])->save();

        try {
            if ($channel->type === 'woocommerce') {
                app(WooCommerceOrderSyncService::class)->sync($channel);
                return;
            }

            $channel->forceFill([
                'sync_status' => 'idle',
                'last_error' => 'Synchronizacja dla typu ' . $channel->type . ' nie jest jeszcze aktywna w v1.4.',
            ])->save();
        } catch (\Throwable $e) {
            $channel->forceFill([
                'sync_status' => 'error',
                'last_error' => mb_substr($e->getMessage(), 0, 2000),
            ])->save();

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        if ($channel = SalesChannel::find($this->salesChannelId)) {
            $channel->forceFill([
                'sync_status' => 'error',
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
        }
    }
}

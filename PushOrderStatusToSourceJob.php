<?php

namespace App\Jobs;

use App\Models\OrderStatusHistory;
use App\Services\Integrations\SalesChannelConnectorResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PushOrderStatusToSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900];

    public function __construct(public int $historyId) {}

    public function handle(SalesChannelConnectorResolver $resolver): void
    {
        $history = OrderStatusHistory::with(['order.salesChannel'])->find($this->historyId);
        if (! $history || $history->sync_status === 'success') return;

        try {
            $result = $resolver->for($history->order->salesChannel)->update($history->order->external_order_id, $history->to_status);
            if (($result['ok'] ?? true) === false) throw new \RuntimeException($result['message'] ?? 'Kanał nie obsługuje tej zmiany statusu.');
            $history->forceFill(['sync_status' => 'success', 'error_message' => null])->save();
        } catch (\Throwable $e) {
            $history->forceFill(['sync_status' => 'failed', 'error_message' => 'Nie udało się zsynchronizować statusu z kanałem.'])->save();
            throw $e;
        }
    }
}

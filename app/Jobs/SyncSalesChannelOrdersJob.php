<?php

namespace App\Jobs;

use App\Models\SalesChannel;
use App\Models\SyncRun;
use App\Services\Integrations\SalesChannelConnectorResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class SyncSalesChannelOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [60, 300, 900];

    public function __construct(public int $salesChannelId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('sales-channel:' . $this->salesChannelId))->expireAfter(180)];
    }

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(30);
    }

    public function handle(): void
    {
        $channel = SalesChannel::find($this->salesChannelId);
        if (!$channel || !$channel->is_enabled) return;

        $channel->forceFill([
            'sync_status' => 'syncing',
            'last_sync_started_at' => now(),
            'last_error' => null,
            'last_error_code' => null,
        ])->save();

        $run = SyncRun::create([
            'company_id' => $channel->company_id,
            'sales_channel_id' => $channel->id,
            'operation' => 'orders.import',
            'status' => 'running',
            'started_at' => now(),
            'correlation_id' => (string) Str::uuid(),
        ]);

        try {
            $stats = app(SalesChannelConnectorResolver::class)->for($channel)->import();
            $run->forceFill([
                'status' => 'success',
                'finished_at' => now(),
                'fetched_count' => $stats['fetched'] ?? 0,
                'created_count' => $stats['created'] ?? 0,
                'updated_count' => $stats['updated'] ?? 0,
            ])->save();
            $this->finish($channel, 'idle', null, null, true, $run);
        } catch (RequestException $e) {
            $status = optional($e->response)->status();
            $code = 'woocommerce_http_' . ($status ?: 'unknown');
            if (in_array($status, [401, 403], true)) {
                $this->finish($channel, 'authentication_error', 'woocommerce_authentication', 'Dane dostępowe WooCommerce są nieprawidłowe lub nie mają dostępu do REST API.', false, $run);
                return;
            }
            $this->finish($channel, $status === 429 ? 'rate_limited' : 'error', $code, 'WooCommerce nie odpowiedział poprawnie. Sprawdź logi po identyfikatorze zdarzenia.', false, $run);
            throw $e;
        } catch (\Throwable $e) {
            $this->finish($channel, 'error', 'sync_failed', 'Synchronizacja nie powiodła się. Sprawdź logi po identyfikatorze zdarzenia.', false, $run);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        if ($channel = SalesChannel::find($this->salesChannelId)) {
            $this->finish($channel, 'error', 'job_failed', 'Synchronizacja nie powiodła się. Sprawdź logi aplikacji.');
        }
    }

    private function finish(SalesChannel $channel, string $status, ?string $code, ?string $message, bool $successful = false, ?SyncRun $run = null): void
    {
        $channel->forceFill([
            'sync_status' => $status,
            'last_sync_finished_at' => now(),
            'last_error_code' => $code,
            'last_error' => $message,
            'consecutive_failures' => $successful ? 0 : ((int) ($channel->consecutive_failures ?? 0) + 1),
            'last_successful_sync_at' => $successful ? now() : $channel->last_successful_sync_at,
        ])->save();

        if ($run && $run->status === 'running') {
            $run->forceFill([
                'status' => $successful ? 'success' : 'failed',
                'finished_at' => now(),
                'error_code' => $code,
                'error_count' => $successful ? 0 : 1,
            ])->save();
        }
    }
}

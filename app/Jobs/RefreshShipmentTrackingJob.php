<?php

namespace App\Jobs;

use App\Models\Shipment;
use App\Models\SyncLog;
use App\Services\Carriers\InPost\InPostClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RefreshShipmentTrackingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $shipmentId) {}

    public $tries = 3;
    public $backoff = [60, 300, 900];

    public function handle(): void
    {
        $shipment = Shipment::findOrFail($this->shipmentId);
        $startedAt = now();

        try {
            match ($shipment->carrier) {
                'inpost' => app(InPostClient::class)->refreshTracking($shipment),
                default => throw new \RuntimeException('Unsupported carrier: ' . $shipment->carrier),
            };

            SyncLog::create([
                'shipment_id' => $shipment->id,
                'type' => 'tracking_sync',
                'status' => 'success',
                'records_count' => 1,
                'started_at' => $startedAt,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            SyncLog::create([
                'shipment_id' => $shipment->id,
                'type' => 'tracking_sync',
                'status' => 'failed',
                'message' => 'Synchronizacja trackingu nie powiodła się. Szczegóły są w logach aplikacji.',
                'started_at' => $startedAt,
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }
}

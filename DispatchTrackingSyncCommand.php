<?php

namespace App\Console\Commands;

use App\Jobs\RefreshShipmentTrackingJob;
use App\Models\Shipment;
use Illuminate\Console\Command;

class DispatchTrackingSyncCommand extends Command
{
    protected $signature = 'commerce-hub:sync-tracking';
    protected $description = 'Dispatch tracking sync jobs for active shipments';

    public function handle(): int
    {
        Shipment::query()
            ->whereNotNull('tracking_number')
            ->whereNotIn('status', ['DELIVERED', 'RETURNED', 'LOST'])
            ->chunkById(100, function ($shipments) {
                foreach ($shipments as $shipment) {
                    RefreshShipmentTrackingJob::dispatch($shipment->id);
                    $this->info('Queued tracking sync for shipment #' . $shipment->id);
                }
            });

        return self::SUCCESS;
    }
}

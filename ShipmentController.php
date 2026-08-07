<?php

namespace App\Http\Controllers;

use App\Jobs\PushTrackingToSourceJob;
use App\Jobs\RefreshShipmentTrackingJob;
use App\Models\CommerceOrder;
use App\Models\Shipment;
use App\Services\Carriers\InPost\InPostClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ShipmentController extends Controller
{
    public function createInPost(Request $request, CommerceOrder $order)
    {
        abort_unless($order->company_id === $this->company()->id, 404);

        $parcel = $request->validate([
            'template' => ['nullable', 'string'],
            'weight' => ['nullable', 'numeric'],
            'service' => ['nullable', 'string'],
        ]);

        $shipment = app(InPostClient::class)->createShipment($order, $parcel);

        return response()->json($shipment, 201);
    }

    public function label(Shipment $shipment)
    {
        abort_unless($shipment->company_id === $this->company()->id, 404);

        $shipment = app(InPostClient::class)->downloadLabel($shipment, 'pdf');

        return Storage::download($shipment->label_path);
    }

    public function refreshTracking(Shipment $shipment)
    {
        abort_unless($shipment->company_id === $this->company()->id, 404);

        RefreshShipmentTrackingJob::dispatch($shipment->id);

        return response()->json(['queued' => true]);
    }

    public function pushTracking(Shipment $shipment)
    {
        abort_unless($shipment->company_id === $this->company()->id, 404);

        PushTrackingToSourceJob::dispatch($shipment->id);

        return response()->json(['queued' => true]);
    }
}

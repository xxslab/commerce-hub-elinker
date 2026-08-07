<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\PushTrackingToSourceJob;
use App\Jobs\RefreshShipmentTrackingJob;
use App\Models\CommerceOrder;
use App\Models\Shipment;
use App\Services\Carriers\InPost\InPostClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ShipmentWebController extends Controller
{
    public function createInPost(Request $request, CommerceOrder $order)
    {
        abort_unless($order->company_id === $this->company()->id, 404);
        $data = $request->validate(['template' => ['nullable', 'string'], 'weight' => ['nullable', 'numeric'], 'service' => ['nullable', 'string']]);
        try {
            app(InPostClient::class)->createShipment($order, $data);
            return back()->with('ok', 'Przesyłka InPost utworzona.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Nie udało się utworzyć przesyłki InPost. Sprawdź konfigurację i logi.');
        }
    }

    public function label(Shipment $shipment)
    {
        abort_unless($shipment->company_id === $this->company()->id, 404);
        try {
            $shipment = app(InPostClient::class)->downloadLabel($shipment, 'pdf');
            return Storage::download($shipment->label_path);
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Nie udało się pobrać etykiety. Sprawdź konfigurację i logi.');
        }
    }

    public function refreshTracking(Shipment $shipment)
    {
        abort_unless($shipment->company_id === $this->company()->id, 404);
        RefreshShipmentTrackingJob::dispatch($shipment->id);
        return back()->with('ok', 'Odświeżanie trackingu dodane do kolejki.');
    }

    public function pushTracking(Shipment $shipment)
    {
        abort_unless($shipment->company_id === $this->company()->id, 404);
        PushTrackingToSourceJob::dispatch($shipment->id);
        return back()->with('ok', 'Wysłanie trackingu do źródła dodane do kolejki.');
    }
}

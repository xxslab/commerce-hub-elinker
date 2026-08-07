<?php

namespace App\Http\Controllers;

use App\Models\CommerceOrder;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return CommerceOrder::query()
            ->with(['salesChannel', 'shipments'])
            ->where('company_id', $this->company()->id)
            ->when($request->input('source'), fn ($q, $v) => $q->where('source', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status_normalized', $v))
            ->when($request->input('q'), function ($q, $v) {
                $q->where(function ($sub) use ($v) {
                    $sub->where('order_number', 'like', "%{$v}%")
                        ->orWhere('customer_name', 'like', "%{$v}%")
                        ->orWhere('customer_email', 'like', "%{$v}%")
                        ->orWhere('external_order_id', 'like', "%{$v}%");
                });
            })
            ->latest('ordered_at')
            ->paginate(50);
    }

    public function show(CommerceOrder $order)
    {
        abort_unless($order->company_id === $this->company()->id, 404);

        return $order->load(['salesChannel', 'items', 'shipments']);
    }

    public function updateLocalStatus(Request $request, CommerceOrder $order)
    {
        abort_unless($order->company_id === $this->company()->id, 404);

        $data = $request->validate([
            'status_normalized' => ['required', 'string', 'max:64'],
        ]);

        $order->forceFill(['status_normalized' => $data['status_normalized']])->save();

        return response()->json($order);
    }
}

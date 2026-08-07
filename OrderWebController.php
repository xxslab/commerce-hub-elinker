<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CommerceOrder;
use App\Models\OrderStatusHistory;
use App\Jobs\PushOrderStatusToSourceJob;
use App\Services\Orders\OrderStatusMapper;
use Illuminate\Http\Request;

class OrderWebController extends Controller
{
    public function index(Request $request)
    {
        $orders = CommerceOrder::query()
            ->where('company_id', $this->company()->id)
            ->with(['salesChannel', 'shipments'])
            ->when($request->input('source'), fn ($q, $v) => $q->where('source', $v))
            ->when($request->input('channel_id'), fn ($q, $v) => $q->where('sales_channel_id', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status_normalized', $v))
            ->when($request->input('payment_status'), fn ($q, $v) => $q->where('payment_status', $v))
            ->when($request->input('currency'), fn ($q, $v) => $q->where('currency', strtoupper($v)))
            ->when($request->input('country'), fn ($q, $v) => $q->where(function ($sub) use ($v) {
                $sub->where('billing_country', strtoupper($v))->orWhere('shipping_country', strtoupper($v));
            }))
            ->when($request->input('from'), fn ($q, $v) => $q->whereDate('ordered_at', '>=', $v))
            ->when($request->input('to'), fn ($q, $v) => $q->whereDate('ordered_at', '<=', $v))
            ->when($request->input('q'), function ($q, $v) {
                $q->where(function ($sub) use ($v) {
                    $sub->where('order_number', 'like', "%{$v}%")
                        ->orWhere('customer_name', 'like', "%{$v}%")
                        ->orWhere('customer_email', 'like', "%{$v}%")
                        ->orWhere('external_order_id', 'like', "%{$v}%");
                });
            })
            ->latest('ordered_at')
            ->paginate(50)
            ->withQueryString();

        return view('orders.index', compact('orders'));
    }

    public function show(CommerceOrder $order)
    {
        abort_unless($order->company_id === $this->company()->id, 404);

        return view('orders.show', [
            'order' => $order->load(['salesChannel', 'items', 'shipments', 'statusHistory']),
        ]);
    }

    public function updateStatus(Request $request, CommerceOrder $order)
    {
        abort_unless($order->company_id === $this->company()->id, 404);

        $data = $request->validate([
            'status_normalized' => ['required', 'in:' . implode(',', OrderStatusMapper::STATUSES)],
        ]);

        $previous = $order->status_normalized;
        $order->forceFill(['status_normalized' => $data['status_normalized']])->save();

        if ($previous !== $data['status_normalized']) {
            $history = OrderStatusHistory::create([
                'company_id' => $order->company_id,
                'commerce_order_id' => $order->id,
                'user_id' => $request->user()->id,
                'from_status' => $previous,
                'to_status' => $data['status_normalized'],
                'source' => 'panel',
                'sync_status' => 'pending',
            ]);
            PushOrderStatusToSourceJob::dispatch($history->id);
        }

        return back()->with('ok', 'Status lokalny zmieniony.');
    }
}

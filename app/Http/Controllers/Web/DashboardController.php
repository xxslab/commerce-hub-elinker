<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CommerceOrder;
use App\Models\Company;
use App\Models\SalesChannel;
use App\Models\Shipment;
use App\Models\SyncRun;
use App\Services\Licensing\SubscriptionEntitlementService;

class DashboardController extends Controller
{
    public function __invoke(SubscriptionEntitlementService $entitlements)
    {
        $company = $this->company();
        $channels = SalesChannel::where('company_id', $company->id)->latest()->get();
        $orders = CommerceOrder::where('company_id', $company->id);

        return view('dashboard.index', [
            'company' => $company,
            'ordersCount' => (clone $orders)->count(),
            'ordersTodayCount' => (clone $orders)->whereDate('ordered_at', now()->toDateString())->count(),
            'ordersNewCount' => (clone $orders)->where('status_normalized', 'NEW')->count(),
            'ordersReadyToShipCount' => (clone $orders)->where('status_normalized', 'READY_TO_SHIP')->count(),
            'ordersShippedCount' => (clone $orders)->whereIn('status_normalized', ['SHIPPED', 'COMPLETED'])->count(),
            'channelsCount' => SalesChannel::query()->when($company, fn ($q) => $q->where('company_id', $company->id))->count(),
            'channels' => $channels,
            'shipmentsCount' => Shipment::query()->when($company, fn ($q) => $q->where('company_id', $company->id))->count(),
            'shipmentsErrorCount' => Shipment::where('company_id', $company->id)->where('status', 'ERROR')->count(),
            'latestOrders' => CommerceOrder::with('salesChannel')->where('company_id', $company->id)->latest('ordered_at')->limit(10)->get(),
            'latestLogs' => SyncRun::where('company_id', $company->id)->with('salesChannel')->latest('started_at')->limit(10)->get(),
            'isActive' => $entitlements->isActive($company),
            'gatingApplicable' => $entitlements->isGatingApplicable($company),
        ]);
    }
}

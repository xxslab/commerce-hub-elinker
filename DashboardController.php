<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CommerceOrder;
use App\Models\Company;
use App\Models\SalesChannel;
use App\Models\Shipment;
use App\Models\SyncRun;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $company = $this->company();
        $channels = SalesChannel::where('company_id', $company->id)->latest()->get();

        return view('dashboard.index', [
            'company' => $company,
            'ordersCount' => CommerceOrder::query()->when($company, fn ($q) => $q->where('company_id', $company->id))->count(),
            'channelsCount' => SalesChannel::query()->when($company, fn ($q) => $q->where('company_id', $company->id))->count(),
            'channels' => $channels,
            'shipmentsCount' => Shipment::query()->when($company, fn ($q) => $q->where('company_id', $company->id))->count(),
            'latestOrders' => CommerceOrder::with('salesChannel')->where('company_id', $company->id)->latest('ordered_at')->limit(10)->get(),
            'latestLogs' => SyncRun::where('company_id', $company->id)->with('salesChannel')->latest('started_at')->limit(10)->get(),
        ]);
    }
}

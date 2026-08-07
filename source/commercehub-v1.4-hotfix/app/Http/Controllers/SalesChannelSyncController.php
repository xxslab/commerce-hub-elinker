<?php

namespace App\Http\Controllers;

use App\Jobs\SyncSalesChannelOrdersJob;
use App\Models\SalesChannel;
use Illuminate\Http\RedirectResponse;

class SalesChannelSyncController extends Controller
{
    public function sync(SalesChannel $salesChannel): RedirectResponse
    {
        $salesChannel->forceFill([
            'sync_status' => 'syncing',
            'last_error' => null,
        ])->save();

        SyncSalesChannelOrdersJob::dispatch($salesChannel->id);

        return back()->with('status', 'Synchronizacja dodana do kolejki: ' . $salesChannel->name);
    }
}

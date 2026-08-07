<?php

use App\Http\Controllers\QueueMonitorController;
use App\Http\Controllers\SalesChannelSyncController;
use App\Models\CommerceOrder;
use App\Models\SalesChannel;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $channels = SalesChannel::orderBy('id')->get();
    $ordersCount = class_exists(CommerceOrder::class) ? CommerceOrder::count() : 0;
    return view('dashboard.index', compact('channels', 'ordersCount'));
})->name('dashboard');

Route::get('/queue', [QueueMonitorController::class, 'index'])->name('queue.index');
Route::post('/integrations/{salesChannel}/sync', [SalesChannelSyncController::class, 'sync'])->name('integrations.sync');

Route::get('/orders', function () {
    $orders = CommerceOrder::with('channel')->orderByDesc('ordered_at')->paginate(50);
    return view('orders.index', compact('orders'));
})->name('orders.index');

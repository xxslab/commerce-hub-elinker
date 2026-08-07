<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\SalesChannelController;
use App\Http\Controllers\ShipmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/sales-channels', [SalesChannelController::class, 'index']);
    Route::post('/sales-channels/woocommerce', [SalesChannelController::class, 'storeWooCommerce']);
    Route::post('/sales-channels/{salesChannel}/test', [SalesChannelController::class, 'test']);
    Route::post('/sales-channels/{salesChannel}/sync', [SalesChannelController::class, 'sync']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::patch('/orders/{order}/local-status', [OrderController::class, 'updateLocalStatus']);

    Route::post('/orders/{order}/shipments/inpost', [ShipmentController::class, 'createInPost']);
    Route::get('/shipments/{shipment}/label', [ShipmentController::class, 'label']);
    Route::post('/shipments/{shipment}/refresh-tracking', [ShipmentController::class, 'refreshTracking']);
    Route::post('/shipments/{shipment}/push-tracking', [ShipmentController::class, 'pushTracking']);
});

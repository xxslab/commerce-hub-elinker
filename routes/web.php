<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\QueueMonitorController;
use App\Http\Controllers\Web\BillingSettingsController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\MarketplaceAppCredentialController;
use App\Http\Controllers\Web\MarketplaceOAuthController;
use App\Http\Controllers\Web\OrderWebController;
use App\Http\Controllers\Web\SalesChannelWebController;
use App\Http\Controllers\Web\ShipmentWebController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.attempt');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:login')->name('register.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/orders', [OrderWebController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderWebController::class, 'show'])->name('orders.show');
    Route::match(['post', 'patch'], '/orders/{order}/status', [OrderWebController::class, 'updateStatus'])->name('orders.status');
    Route::get('/queue', [QueueMonitorController::class, 'index'])->name('queue.index');
    Route::post('/queue/failed/clear', [QueueMonitorController::class, 'clearFailed'])->middleware('company.admin')->name('queue.failed.clear');

    Route::get('/channels', [SalesChannelWebController::class, 'index'])->name('channels.index');
    Route::get('/channels/woocommerce/create', [SalesChannelWebController::class, 'createWooCommerce'])->name('channels.woocommerce.create');
    Route::post('/channels/woocommerce', [SalesChannelWebController::class, 'storeWooCommerce'])->middleware('subscription.active')->name('channels.woocommerce.store');
    Route::get('/channels/{salesChannel}/edit', [SalesChannelWebController::class, 'edit'])->name('channels.edit');
    Route::patch('/channels/{salesChannel}', [SalesChannelWebController::class, 'update'])->middleware('company.admin')->name('channels.update');
    Route::post('/channels/{salesChannel}/test', [SalesChannelWebController::class, 'test'])->name('channels.test');
    Route::post('/channels/{salesChannel}/sync', [SalesChannelWebController::class, 'sync'])->middleware('subscription.active')->name('channels.sync');
    Route::post('/channels/{salesChannel}/toggle', [SalesChannelWebController::class, 'toggle'])->name('channels.toggle');
    Route::delete('/channels/{salesChannel}', [SalesChannelWebController::class, 'destroy'])->middleware('company.admin')->name('channels.destroy');

    Route::get('/integrations/{marketplace}/apps', [MarketplaceAppCredentialController::class, 'index'])->name('marketplace-apps.index');
    Route::get('/integrations/{marketplace}/apps/create', [MarketplaceAppCredentialController::class, 'create'])->name('marketplace-apps.create');
    Route::post('/integrations/{marketplace}/apps', [MarketplaceAppCredentialController::class, 'store'])->name('marketplace-apps.store');
    Route::get('/marketplace-apps/{credential}/edit', [MarketplaceAppCredentialController::class, 'edit'])->name('marketplace-apps.edit');
    Route::put('/marketplace-apps/{credential}', [MarketplaceAppCredentialController::class, 'update'])->name('marketplace-apps.update');

    Route::get('/integrations/allegro/connect', [MarketplaceOAuthController::class, 'connectAllegro'])->middleware('subscription.active')->name('integrations.allegro.connect');
    Route::get('/integrations/allegro/callback', [MarketplaceOAuthController::class, 'callbackAllegro'])->name('integrations.allegro.callback');
    Route::get('/integrations/ebay/connect', [MarketplaceOAuthController::class, 'connectEbay'])->middleware('subscription.active')->name('integrations.ebay.connect');
    Route::get('/integrations/ebay/callback', [MarketplaceOAuthController::class, 'callbackEbay'])->name('integrations.ebay.callback');
    Route::post('/channels/{salesChannel}/refresh-token', [MarketplaceOAuthController::class, 'refreshToken'])->name('channels.refreshToken');

    Route::post('/orders/{order}/shipments/inpost', [ShipmentWebController::class, 'createInPost'])->middleware('subscription.active')->name('shipments.inpost.create');
    Route::get('/shipments/{shipment}/label', [ShipmentWebController::class, 'label'])->name('shipments.label');
    Route::post('/shipments/{shipment}/refresh-tracking', [ShipmentWebController::class, 'refreshTracking'])->name('shipments.refreshTracking');
    Route::post('/shipments/{shipment}/push-tracking', [ShipmentWebController::class, 'pushTracking'])->name('shipments.pushTracking');

    Route::get('/settings/billing', [BillingSettingsController::class, 'show'])->name('settings.billing');
    Route::post('/settings/billing/connect', [BillingSettingsController::class, 'connect'])->middleware('company.admin')->name('settings.billing.connect');
    Route::post('/settings/billing/disconnect', [BillingSettingsController::class, 'disconnect'])->middleware('company.admin')->name('settings.billing.disconnect');
    Route::post('/settings/billing/refresh', [BillingSettingsController::class, 'refresh'])->middleware('company.admin')->name('settings.billing.refresh');
});

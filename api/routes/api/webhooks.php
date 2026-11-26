<?php

use App\Http\Controllers\Audience\DeliveryReportController;
use App\Http\Controllers\Audience\InboundSmsController;
use App\Http\Controllers\Audience\OpenTrackingController;
use App\Http\Controllers\Billing\FlutterwaveWebhookController;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/inbound/{connection:ulid}', InboundSmsController::class)->name('webhooks.inbound');
Route::post('webhooks/status/{connection:ulid}', DeliveryReportController::class)->name('webhooks.status');
Route::post('webhooks/flutterwave', FlutterwaveWebhookController::class)->name('webhooks.flutterwave');

// Mail image proxies fetch pixels for many recipients from a few addresses, so the per-IP API limit would drop opens.
Route::get('track/open/{message}', OpenTrackingController::class)
    ->whereNumber('message')
    ->withoutMiddleware(ThrottleRequests::class . ':api')
    ->name('track.open');

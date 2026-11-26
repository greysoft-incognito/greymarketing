<?php

use App\Http\Controllers\Audience\DeliveryReportController;
use App\Http\Controllers\Audience\InboundSmsController;
use App\Http\Controllers\Audience\EmailTrackingController;
use App\Http\Controllers\Billing\FlutterwaveWebhookController;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/inbound/{connection:ulid}', InboundSmsController::class)->name('webhooks.inbound');
Route::post('webhooks/status/{connection:ulid}', DeliveryReportController::class)->name('webhooks.status');
Route::post('webhooks/flutterwave', FlutterwaveWebhookController::class)->name('webhooks.flutterwave');

// Mail image proxies and link scanners fetch for many recipients from a few addresses, so the per-IP API limit would drop them.
Route::controller(EmailTrackingController::class)
    ->whereNumber('message')
    ->withoutMiddleware(ThrottleRequests::class . ':api')
    ->group(function () {
        Route::get('track/open/{message}', 'open')->name('track.open');
        Route::get('track/click/{message}', 'click')->name('track.click');
    });

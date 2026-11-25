<?php

use App\Http\Controllers\Audience\DeliveryReportController;
use App\Http\Controllers\Audience\InboundSmsController;
use App\Http\Controllers\Billing\FlutterwaveWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/inbound/{connection:ulid}', InboundSmsController::class)->name('webhooks.inbound');
Route::post('webhooks/status/{connection:ulid}', DeliveryReportController::class)->name('webhooks.status');
Route::post('webhooks/flutterwave', FlutterwaveWebhookController::class)->name('webhooks.flutterwave');

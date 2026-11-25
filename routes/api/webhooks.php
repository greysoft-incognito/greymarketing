<?php

use App\Http\Controllers\Audience\InboundSmsController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/inbound/{connection:ulid}', InboundSmsController::class)->name('webhooks.inbound');

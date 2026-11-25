<?php

use App\Http\Controllers\Workspace\ProviderConnectionController;
use App\Http\Controllers\Workspace\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('providers', [ProviderConnectionController::class, 'catalog'])->name('providers.catalog');

    Route::apiResource('workspaces', WorkspaceController::class);

    Route::scopeBindings()->prefix('workspaces/{workspace}')->name('workspaces.')->group(function () {
        Route::apiResource('connections', ProviderConnectionController::class);
        Route::post('connections/{connection}/test', [ProviderConnectionController::class, 'test'])->name('connections.test');
        Route::post('connections/{connection}/send-test', [ProviderConnectionController::class, 'sendTest'])->name('connections.send-test');
    });
});

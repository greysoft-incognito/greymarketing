<?php

use App\Http\Controllers\Workspace\ContactController;
use App\Http\Controllers\Workspace\ContactImportController;
use App\Http\Controllers\Workspace\ContactListController;
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

        Route::apiResource('contacts', ContactController::class);

        Route::apiResource('lists', ContactListController::class);
        Route::post('lists/{list}/contacts', [ContactListController::class, 'attach'])->name('lists.contacts.attach');
        Route::delete('lists/{list}/contacts', [ContactListController::class, 'detach'])->name('lists.contacts.detach');

        Route::apiResource('imports', ContactImportController::class)->only(['index', 'store', 'show']);
        Route::post('imports/{import}/start', [ContactImportController::class, 'start'])->name('imports.start');
    });
});

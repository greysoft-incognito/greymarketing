<?php

use App\Http\Controllers\Audience\CountryController;
use App\Http\Controllers\Billing\PlanController;
use App\Http\Controllers\Workspace\BillingController;
use App\Http\Controllers\Workspace\CampaignController;
use App\Http\Controllers\Workspace\ContactController;
use App\Http\Controllers\Workspace\ContactImportController;
use App\Http\Controllers\Workspace\ContactListController;
use App\Http\Controllers\Workspace\ProviderConnectionController;
use App\Http\Controllers\Workspace\ReportController;
use App\Http\Controllers\Workspace\StatsController;
use App\Http\Controllers\Workspace\TemplateController;
use App\Http\Controllers\Workspace\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('plans', PlanController::class)->name('plans.index');
Route::get('countries', CountryController::class)->name('countries.index');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('providers', [ProviderConnectionController::class, 'catalog'])->name('providers.catalog');

    Route::apiResource('workspaces', WorkspaceController::class);
    Route::post('workspaces/{workspace}/leave', [WorkspaceController::class, 'leave'])->name('workspaces.leave');

    Route::scopeBindings()->prefix('workspaces/{workspace}')->name('workspaces.')->group(function () {
        Route::get('stats', StatsController::class)->name('stats');
        Route::get('reports', [ReportController::class, 'show'])->name('reports.show');
        Route::get('reports/campaigns', [ReportController::class, 'campaigns'])->name('reports.campaigns');

        Route::apiResource('connections', ProviderConnectionController::class);
        Route::post('connections/{connection}/test', [ProviderConnectionController::class, 'test'])->name('connections.test');
        Route::post('connections/{connection}/send-test', [ProviderConnectionController::class, 'sendTest'])->name('connections.send-test');

        Route::apiResource('contacts', ContactController::class);

        Route::apiResource('lists', ContactListController::class);
        Route::post('lists/{list}/contacts', [ContactListController::class, 'attach'])->name('lists.contacts.attach');
        Route::delete('lists/{list}/contacts', [ContactListController::class, 'detach'])->name('lists.contacts.detach');

        Route::apiResource('imports', ContactImportController::class)->only(['index', 'store', 'show']);
        Route::post('imports/{import}/start', [ContactImportController::class, 'start'])->name('imports.start');

        Route::apiResource('templates', TemplateController::class);
        Route::post('templates/{template}/preview', [TemplateController::class, 'preview'])->name('templates.preview');

        Route::apiResource('campaigns', CampaignController::class);
        Route::post('campaigns/{campaign}/launch', [CampaignController::class, 'launch'])->name('campaigns.launch');
        Route::post('campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('campaigns.cancel');
        Route::get('campaigns/{campaign}/report', [CampaignController::class, 'report'])->name('campaigns.report');
        Route::get('campaigns/{campaign}/messages', [CampaignController::class, 'messages'])->name('campaigns.messages');

        Route::get('billing', [BillingController::class, 'show'])->name('billing.show');
        Route::post('billing/subscribe', [BillingController::class, 'subscribe'])->name('billing.subscribe');
        Route::post('billing/verify', [BillingController::class, 'verify'])->name('billing.verify');
        Route::post('billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
        Route::get('billing/transactions', [BillingController::class, 'transactions'])->name('billing.transactions');
    });
});

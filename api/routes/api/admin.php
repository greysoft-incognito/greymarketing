<?php

use App\Http\Controllers\Admin\ConfigurationController;
use App\Http\Controllers\Admin\NotificationTemplateController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WorkspaceController;
use Illuminate\Support\Facades\Route;

$permissionMiddlewares = 'role:' . implode('|', config('permission-defs.roles', []));

Route::middleware(['auth:sanctum', $permissionMiddlewares])->prefix('admin')->group(function () {
    Route::apiResource('users', UserController::class);

    Route::apiResource('configurations', ConfigurationController::class)->only(['index', 'show', 'store']);

    // Notifications Templates
    Route::apiResource('configurations/notifications/templates', NotificationTemplateController::class)
        ->except(['store']);

    Route::apiResource('plans', PlanController::class)->names('admin.plans');

    Route::get('workspaces', [WorkspaceController::class, 'index'])->name('admin.workspaces.index');
    Route::get('workspaces/{workspace}', [WorkspaceController::class, 'show'])->name('admin.workspaces.show');
    Route::post('workspaces/{workspace}/subscription', [WorkspaceController::class, 'grant'])->name('admin.workspaces.grant');

    Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('admin.subscriptions.index');
    Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('admin.subscriptions.cancel');

    Route::get('transactions', [TransactionController::class, 'index'])->name('admin.transactions.index');
});

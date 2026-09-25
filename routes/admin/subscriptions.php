<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\EmployeeAiPointsController;
use App\Http\Controllers\Admin\SubscriptionsController;
use Illuminate\Support\Facades\Route;

Route::middleware(Permission::SubscriptionsManage->middleware())->group(function () {
    Route::get('subscriptions', [SubscriptionsController::class, 'index'])->name('subscriptions');
    Route::patch('subscriptions/plans/{plan}', [SubscriptionsController::class, 'updatePlan'])->name('subscriptions.plans.update');
    Route::put('subscriptions/hotel-plan', [SubscriptionsController::class, 'assignPlan'])->name('subscriptions.hotel-plan');
    Route::post('subscriptions/payment-methods', [SubscriptionsController::class, 'storePaymentMethod'])->name('subscriptions.payment-methods.store');
    Route::patch('subscriptions/payment-methods/{paymentMethod}', [SubscriptionsController::class, 'updatePaymentMethod'])->name('subscriptions.payment-methods.update');
});

Route::middleware(Permission::AiPointsManage->middleware())->group(function () {
    Route::get('ai-points', [EmployeeAiPointsController::class, 'index'])->name('ai-points');
    Route::patch('ai-points/employees/{employee}', [EmployeeAiPointsController::class, 'update'])->name('ai-points.update');
});

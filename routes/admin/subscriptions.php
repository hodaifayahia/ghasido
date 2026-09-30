<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\EmployeeAiPointsController;
use App\Http\Controllers\Admin\HotelAiPointTopUpRequestController;
use App\Http\Controllers\Admin\IndividualsController;
use App\Http\Controllers\Admin\SubscriptionsController;
use Illuminate\Support\Facades\Route;

Route::middleware(Permission::SubscriptionsManage->middleware())->group(function () {
    Route::get('subscriptions', [SubscriptionsController::class, 'index'])->name('subscriptions');
    Route::patch('subscriptions/plans/{plan}', [SubscriptionsController::class, 'updatePlan'])->name('subscriptions.plans.update');
    Route::put('subscriptions/hotel-plan', [SubscriptionsController::class, 'assignPlan'])->name('subscriptions.hotel-plan');
    Route::post('subscriptions/ai-point-topups', [SubscriptionsController::class, 'recordAiPointPayment'])->name('subscriptions.ai-point-topups.store');
    Route::post('subscriptions/ai-point-top-up-requests/{topUpRequest}/read', [HotelAiPointTopUpRequestController::class, 'read'])->name('subscriptions.ai-point-top-up-requests.read');
    Route::post('subscriptions/payment-methods', [SubscriptionsController::class, 'storePaymentMethod'])->name('subscriptions.payment-methods.store');
    Route::patch('subscriptions/payment-methods/{paymentMethod}', [SubscriptionsController::class, 'updatePaymentMethod'])->name('subscriptions.payment-methods.update');

    // Individual subscribers: learners with no hotel (user request 2026-09-25).
    Route::get('individuals', [IndividualsController::class, 'index'])->name('individuals');
    Route::post('individuals', [IndividualsController::class, 'store'])->name('individuals.store');
    Route::patch('individuals/{individual}', [IndividualsController::class, 'update'])->name('individuals.update');
    Route::post('individuals/{individual}/toggle', [IndividualsController::class, 'toggle'])->name('individuals.toggle');
    // Bought online: confirm or refuse the payment (client request 2026-09-27).
    Route::post('individuals/{individual}/approve', [IndividualsController::class, 'approve'])->name('individuals.approve');
    Route::post('individuals/{individual}/reject', [IndividualsController::class, 'reject'])->name('individuals.reject');
});

Route::middleware(Permission::AiPointsManage->middleware())->group(function () {
    Route::get('ai-points', [EmployeeAiPointsController::class, 'index'])->name('ai-points');
    Route::patch('ai-points/employees/{employee}', [EmployeeAiPointsController::class, 'update'])->name('ai-points.update');
    Route::post('ai-points/request-top-up', [EmployeeAiPointsController::class, 'requestTopUp'])->name('ai-points.request-top-up');
});

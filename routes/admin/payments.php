<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\PaymentsController;
use Illuminate\Support\Facades\Route;

// Payments sent from the checkout (client request 2026-09-27).
Route::middleware(Permission::SubscriptionsManage->middleware())->group(function () {
    Route::get('payments', [PaymentsController::class, 'index'])->name('payments');
    Route::get('payments/{payment}/receipt', [PaymentsController::class, 'receipt'])->name('payments.receipt');
    Route::post('payments/{payment}/read', [PaymentsController::class, 'read'])->name('payments.read');
    Route::post('payments/{payment}/message', [PaymentsController::class, 'message'])
        ->middleware('throttle:20,1')
        ->name('payments.message');
});

<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\DeleteController;
use App\Http\Controllers\Admin\Hotels\HotelAccessController;
use App\Http\Controllers\Admin\Hotels\HotelApprovalController;
use App\Http\Controllers\Admin\Hotels\HotelContractController;
use App\Http\Controllers\Admin\Hotels\HotelDepartmentsController;
use App\Http\Controllers\Admin\Hotels\HotelSeatQuotasController;
use App\Http\Controllers\Admin\HotelsController;
use Illuminate\Support\Facades\Route;

// Hotels (spec 0002, API surface). Included from routes/admin.php inside the
// auth group. Every route carries the permission it needs; the policy check
// inside each action is the authorization proper (ROLE-01, SEC-01).
Route::get('hotels', [HotelsController::class, 'index'])
    ->middleware(Permission::HotelsView->middleware())
    ->name('hotels');

Route::get('hotels/{hotel}', [HotelsController::class, 'show'])
    ->middleware(Permission::HotelsView->middleware())
    ->name('hotels.show');

Route::middleware(Permission::HotelsManage->middleware())->group(function () {
    Route::post('hotels', [HotelsController::class, 'store'])->name('hotels.store');
    Route::patch('hotels/{hotel}', [HotelsController::class, 'update'])->name('hotels.update');
    Route::post('hotels/{hotel}/archive', [HotelAccessController::class, 'archive'])->name('hotels.archive');
    Route::post('hotels/{hotel}/pause', [HotelAccessController::class, 'pause'])->name('hotels.pause');
    Route::post('hotels/{hotel}/resume', [HotelAccessController::class, 'resume'])->name('hotels.resume');
    Route::patch('hotels/{hotel}/contract', [HotelContractController::class, 'update'])->name('hotels.contract');
    Route::put('hotels/{hotel}/seat-quotas', [HotelSeatQuotasController::class, 'update'])->name('hotels.seat-quotas');
    Route::post('hotels/{hotel}/departments', [HotelDepartmentsController::class, 'store'])->name('hotels.departments.store');
    Route::delete('hotels/{hotel}/departments/{department}', [HotelDepartmentsController::class, 'destroy'])->name('hotels.departments.destroy');
    // Safe delete (owner decision 2026-09-27): refused while anything depends on the row.
    Route::delete('hotels/{hotel}', [DeleteController::class, 'hotel'])->name('hotels.destroy');
});

Route::middleware(Permission::HotelsApprove->middleware())->group(function () {
    Route::post('hotels/{hotel}/approve', [HotelApprovalController::class, 'approve'])->name('hotels.approve');
    Route::post('hotels/{hotel}/reject', [HotelApprovalController::class, 'reject'])->name('hotels.reject');
});

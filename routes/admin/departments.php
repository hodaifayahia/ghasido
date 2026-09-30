<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\DepartmentsController;
use Illuminate\Support\Facades\Route;

// Departments (spec 0003, Part D). Included from routes/admin.php inside the
// auth group. Every route carries the permission it needs; the policy check
// inside each action is the authorization proper (ROLE-01, SEC-01).
Route::get('departments', [DepartmentsController::class, 'index'])
    ->middleware(Permission::DepartmentsView->middleware())
    ->name('departments');

Route::middleware(Permission::DepartmentsManage->middleware())->group(function () {
    Route::post('departments', [DepartmentsController::class, 'store'])->name('departments.store');
    Route::patch('departments/{department}', [DepartmentsController::class, 'update'])->name('departments.update');
    Route::post('departments/{department}/toggle', [DepartmentsController::class, 'toggle'])->name('departments.toggle');
});

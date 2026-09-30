<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\Employees\EmployeeBulkController;
use App\Http\Controllers\Admin\Employees\EmployeeExportController;
use App\Http\Controllers\Admin\Employees\EmployeePasswordController;
use App\Http\Controllers\Admin\Employees\EmployeeReminderController;
use App\Http\Controllers\Admin\Employees\EmployeeStatusController;
use App\Http\Controllers\Admin\EmployeesController;
use Illuminate\Support\Facades\Route;

// Manage Employees (spec 0003, Part D). Included from routes/admin.php inside
// the auth group. Every route carries the permission it needs; the policy
// check inside each action is the authorization proper (ROLE-01, SEC-01).
Route::middleware(Permission::EmployeesView->middleware())->group(function () {
    Route::get('employees', [EmployeesController::class, 'index'])->name('employees');
    // Declared before employees/{employee} so "export" is never read as an id.
    Route::get('employees/export', [EmployeeExportController::class, 'download'])
        ->middleware('english-data')
        ->name('employees.export');
});

// Adding an account is its own capability: a Manager may edit but not create
// one (a client restriction narrowing SUB-02). Super Admin and Admin hold it.
Route::middleware(Permission::EmployeesCreate->middleware())->group(function () {
    Route::post('employees', [EmployeesController::class, 'store'])->name('employees.store');
});

Route::middleware(Permission::EmployeesManage->middleware())->group(function () {
    Route::post('employees/bulk', [EmployeeBulkController::class, 'apply'])->name('employees.bulk');
    Route::post('employees/remind', [EmployeeReminderController::class, 'send'])->name('employees.remind');
    Route::patch('employees/{employee}', [EmployeesController::class, 'update'])->name('employees.update');
    Route::post('employees/{employee}/activate', [EmployeeStatusController::class, 'activate'])->name('employees.activate');
    Route::post('employees/{employee}/deactivate', [EmployeeStatusController::class, 'deactivate'])->name('employees.deactivate');
    Route::post('employees/{employee}/reset-password', [EmployeePasswordController::class, 'reset'])->name('employees.reset-password');
});

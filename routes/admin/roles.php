<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\RoleController;
use Illuminate\Support\Facades\Route;

// Roles & Permissions (client request 2026-09-23). Included from
// routes/admin.php inside the auth group. Viewing needs roles.view; every
// write needs roles.manage. Only the Super Admin holds either, so this
// screen is the platform owner's (ROLE-01, SEC-01; spec 0001).
Route::get('roles', [RoleController::class, 'index'])
    ->middleware(Permission::RolesView->middleware())
    ->name('roles');

Route::middleware(Permission::RolesManage->middleware())->group(function () {
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
});

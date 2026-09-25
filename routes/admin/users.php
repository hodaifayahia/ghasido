<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\UsersController;
use Illuminate\Support\Facades\Route;

// App access accounts are separate from hotel learners. Reading and changing
// them have their own capabilities; granting roles never means an account can
// give away permissions it does not already hold (ROLE-01, AUTH-03, SEC-01).
Route::get('users', [UsersController::class, 'index'])
    ->middleware(Permission::UsersView->middleware())
    ->name('users');

Route::middleware(Permission::UsersManage->middleware())->group(function () {
    Route::post('users', [UsersController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [UsersController::class, 'update'])->name('users.update');
});

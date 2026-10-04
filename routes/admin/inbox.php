<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\InboxController;
use Illuminate\Support\Facades\Route;

// The Super Admin's inbox of contact and support messages (client request
// 2026-10-03).
Route::middleware(Permission::LandingManage->middleware())->group(function () {
    Route::get('inbox', [InboxController::class, 'index'])->name('inbox');
    Route::post('inbox/{contactMessage}/reply', [InboxController::class, 'reply'])
        ->middleware('throttle:30,1')
        ->name('inbox.reply');
    // Level change requests (client request 2026-10-04).
    Route::post('inbox/level-requests/{levelRequest}/decide', [InboxController::class, 'decideLevel'])
        ->name('inbox.level-requests.decide');
    Route::post('inbox/level-requests/{levelRequest}/seen', [InboxController::class, 'seenLevel'])
        ->name('inbox.level-requests.seen');
});

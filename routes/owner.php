<?php

use App\Http\Controllers\Owner\ApiAccountController;
use App\Http\Controllers\Owner\ApiKeyController;
use App\Http\Controllers\Owner\CreditTopupController;
use App\Http\Controllers\Owner\ModelPriceController;
use App\Http\Controllers\Owner\OwnerConsoleController;
use App\Http\Controllers\Owner\OwnerSessionController;
use Illuminate\Support\Facades\Route;

/*
| The platform owner's console (spec 0007): API keys, credit and prices for
| the paid AI accounts. Its own `owner` guard: no app user, the Super Admin
| included, passes the `owner` middleware (D1).
*/

Route::prefix('owner')->name('owner.')->group(function (): void {
    Route::get('login', [OwnerSessionController::class, 'create'])->name('login');
    Route::post('login', [OwnerSessionController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('login.store');

    Route::middleware('owner')->group(function (): void {
        Route::post('logout', [OwnerSessionController::class, 'destroy'])->name('logout');

        Route::get('/', OwnerConsoleController::class)->name('dashboard');

        Route::put('accounts/{account}/key', [ApiKeyController::class, 'update'])->name('accounts.key.update');
        Route::delete('accounts/{account}/key', [ApiKeyController::class, 'destroy'])->name('accounts.key.destroy');
        Route::post('accounts/{account}/topups', [CreditTopupController::class, 'store'])->name('accounts.topups.store');
        Route::patch('accounts/{account}/pause', [ApiAccountController::class, 'pause'])->name('accounts.pause');
        Route::post('accounts/{account}/check', [ApiAccountController::class, 'check'])
            ->middleware('throttle:10,1')
            ->name('accounts.check');
        Route::post('accounts/deepgram/balance', [ApiAccountController::class, 'balance'])
            ->middleware('throttle:10,1')
            ->name('accounts.balance');

        Route::put('prices', [ModelPriceController::class, 'update'])->name('prices.update');
    });
});

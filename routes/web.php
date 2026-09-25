<?php

use App\Enums\Role;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HotelSignupController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\Learn\MessagesController;
use App\Http\Controllers\MediaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', LandingPageController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('checkout/{plan:slug}', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('checkout/{plan:slug}', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('hotel-signup', [HotelSignupController::class, 'create'])->name('hotel-signup');
    Route::post('hotel-signup', [HotelSignupController::class, 'store'])->name('hotel-signup.store');
});

// Signed in is enough: the role decides which dashboard renders, so a user
// without the admin permissions gets the placeholder rather than a 403
// (spec 0001, AC-7). `verified` is dropped here for the same reason as in
// admin.php: User never implements MustVerifyEmail, so it guarded nothing.
Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('help', function (Request $request) {
        $page = $request->user()->hasRole(Role::Employee->value)
            ? 'employee/Help'
            : 'Help';

        return Inertia::render($page);
    })->name('help');
    Route::post('notifications/{reminder}/read', [MessagesController::class, 'read'])
        ->name('notifications.read');

    // Private files (learner recordings, exports) are served only through
    // here, behind MediaAssetPolicy (PRIV-04, SEC-04; spec 0003 B.3). Any
    // signed in role may ask; the policy decides.
    Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');
});

require __DIR__.'/admin.php';
require __DIR__.'/learn.php';
require __DIR__.'/settings.php';

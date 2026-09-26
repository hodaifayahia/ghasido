<?php

use App\Enums\Role;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HotelSignupController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\Learn\MessagesController;
use App\Http\Controllers\MeaningController;
use App\Http\Controllers\MediaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', LandingPageController::class)->name('home');

// Public Contact Us page (client decision 2026-09-26). Open to everyone,
// signed in or not; the form is throttled against abuse.
Route::get('contact', [ContactController::class, 'show'])->name('contact');
Route::post('contact', [ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');

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
Route::middleware(['auth', 'hotel.access'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('help', function (Request $request) {
        $page = $request->user('web')?->hasRole(Role::Employee->value)
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

    // Show Meaning on any English text (CTRL-01..03; client decision
    // 2026-09-26). The tapped button polls the same address while the
    // translation is queued, hence the generous limit.
    Route::post('meaning', MeaningController::class)
        ->middleware('throttle:120,1')
        ->name('meaning');
});

require __DIR__.'/admin.php';
require __DIR__.'/learn.php';
require __DIR__.'/owner.php';
require __DIR__.'/settings.php';

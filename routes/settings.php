<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\LandingPageController;
use App\Http\Controllers\Settings\AiModelsController;
use App\Http\Controllers\Settings\AiUsageController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Services\Ai\AiModelSettings;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'hotel.access'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Public landing copy is editable by the platform owner (ADM-02, SEC-01).
    Route::middleware(Permission::LandingManage->middleware())->group(function (): void {
        Route::get('settings/landing-page', [LandingPageController::class, 'edit'])->name('landing-page.edit');
        Route::patch('settings/landing-page', [LandingPageController::class, 'update'])->name('landing-page.update');
        Route::patch('settings/contact-messages/{contactMessage}/read', [LandingPageController::class, 'markContactMessageRead'])->name('contact-messages.read');
    });
});

Route::middleware(['auth', 'verified', 'hotel.access'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});

// Settings → AI models: Super Admin only, a 403 for everyone else (API-04,
// ROLE-01, SEC-01). The gate is repeated in the controller.
Route::middleware(['auth', 'can:manage-ai-models'])->group(function () {
    Route::get('settings/ai-models', [AiModelsController::class, 'edit'])->name('ai-models.edit');
    Route::patch('settings/ai-models', [AiModelsController::class, 'update'])->name('ai-models.update');
    Route::post('settings/ai-models/check/{capability}', [AiModelsController::class, 'check'])
        ->whereIn('capability', AiModelSettings::CHECKS)
        ->middleware('throttle:20,1')
        ->name('ai-models.check');

    // Settings → AI usage: cost by feature, model, hotel and day (API-03,
    // AIL-04; spec 0005 §4.3). The prices behind it are the platform
    // owner's, edited on the owner console (spec 0007, D8).
    Route::get('settings/ai-usage', [AiUsageController::class, 'index'])->name('ai-usage.index');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');

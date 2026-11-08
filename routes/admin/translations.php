<?php

use App\Http\Controllers\Admin\TranslationsController;
use Illuminate\Support\Facades\Route;

// Show Meaning translations (user request 2026-09-26). Included from
// routes/admin.php inside the auth group. Lessons or tests editors only; the
// controller checks it on every action (ROLE-01, SEC-01).
Route::get('translations', [TranslationsController::class, 'index'])->name('translations');
Route::put('translations', [TranslationsController::class, 'save'])->name('translations.save');
Route::post('translations/generate', [TranslationsController::class, 'generate'])
    ->middleware('throttle:10,1')
    ->name('translations.generate');

// The "Translate meaning to Arabic" button above each English field of the
// test and lesson builders (client request 2026-09-29). JSON.
Route::post('translations/lookup', [TranslationsController::class, 'lookup'])->name('translations.lookup');
// Throttled: each call spends the platform owner's AI credit (security
// audit 2026-11-08).
Route::post('translations/draft', [TranslationsController::class, 'draft'])
    ->middleware('throttle:30,1')
    ->name('translations.draft');
Route::post('translations/write', [TranslationsController::class, 'write'])->name('translations.write');

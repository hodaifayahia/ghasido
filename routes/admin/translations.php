<?php

use App\Http\Controllers\Admin\TranslationsController;
use Illuminate\Support\Facades\Route;

// Show Meaning translations (user request 2026-09-26). Included from
// routes/admin.php inside the auth group. Lessons or tests editors only; the
// controller checks it on every action (ROLE-01, SEC-01).
Route::get('translations', [TranslationsController::class, 'index'])->name('translations');
Route::put('translations', [TranslationsController::class, 'save'])->name('translations.save');
// The builders' Translation buttons read the current meanings (user request 2026-09-26).
Route::post('translations/lookup', [TranslationsController::class, 'lookup'])->name('translations.lookup');
Route::post('translations/generate', [TranslationsController::class, 'generate'])->name('translations.generate');

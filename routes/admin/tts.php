<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\TtsSettingsController;
use Illuminate\Support\Facades\Route;

// Global speech controls are platform-owner settings. Managers can use
// generated lesson audio but cannot change the platform voice (ROLE-01,
// API-02, API-04; spec 0001).
Route::post('tts/settings', [TtsSettingsController::class, 'update'])
    ->middleware(Permission::ScenariosManage->middleware())
    ->name('tts.settings.update');

<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\AiScenarioPreviewController;
use App\Http\Controllers\Admin\AiScenariosController;
use App\Http\Controllers\Admin\AiScenarioVoiceCallController;
use App\Http\Controllers\Admin\VoiceAgentSettingsController;
use Illuminate\Support\Facades\Route;

// AI Scenarios. Included from routes/admin.php inside the auth group.
Route::get('ai-scenarios', AiScenariosController::class)
    ->middleware(Permission::ScenariosView->middleware())
    ->name('ai-scenarios');

// Live "Preview & Test" role-play (RP-13): a real Qwen + Deepgram
// conversation flagged is_preview. Authoring, so it needs manage.
Route::middleware(Permission::ScenariosManage->middleware())->group(function () {
    Route::post('ai-scenarios', [AiScenariosController::class, 'store'])
        ->name('ai-scenarios.store');
    Route::patch('ai-scenarios/config/categories', [AiScenariosController::class, 'updateCategories'])
        ->name('ai-scenarios.config.categories');
    Route::patch('ai-scenarios/config/instructions', [AiScenariosController::class, 'updateInstructions'])
        ->name('ai-scenarios.config.instructions');
    Route::patch('ai-scenarios/config/feedback', [AiScenariosController::class, 'updateFeedback'])
        ->name('ai-scenarios.config.feedback');
    Route::patch('ai-scenarios/{scenario}', [AiScenariosController::class, 'update'])
        ->name('ai-scenarios.update');
    Route::delete('ai-scenarios/{scenario}', [AiScenariosController::class, 'destroy'])
        ->name('ai-scenarios.destroy');
    Route::post('ai-scenarios/{scenario}/generate', [AiScenariosController::class, 'generate'])
        ->name('ai-scenarios.generate');
    Route::post('ai-scenarios/{scenario}/apply-draft', [AiScenariosController::class, 'applyDraft'])
        ->name('ai-scenarios.apply-draft');
    Route::post('ai-scenarios/{scenario}/publish', [AiScenariosController::class, 'publish'])
        ->name('ai-scenarios.publish');

    Route::post('ai-scenarios/preview', [AiScenarioPreviewController::class, 'store'])
        ->name('ai-scenarios.preview.store');
    Route::post('ai-scenarios/preview/{attempt}/message', [AiScenarioPreviewController::class, 'message'])
        ->name('ai-scenarios.preview.message');
    Route::post('ai-scenarios/preview/{attempt}/end', [AiScenarioPreviewController::class, 'end'])
        ->name('ai-scenarios.preview.end');
    Route::delete('ai-scenarios/preview/{attempt}', [AiScenarioPreviewController::class, 'destroy'])
        ->name('ai-scenarios.preview.destroy');

    // Live voice call (Deepgram Voice Agent, spec 0004): global settings,
    // per-scenario voice overrides, and the admin "Test voice call" (RP-13).
    Route::patch('ai-scenarios/voice-agent/settings', [VoiceAgentSettingsController::class, 'update'])
        ->name('ai-scenarios.voice-agent.update');
    Route::patch('ai-scenarios/{scenario}/voice-agent', [VoiceAgentSettingsController::class, 'updateScenario'])
        ->name('ai-scenarios.voice-agent.scenario');
    Route::post('ai-scenarios/{scenario}/voice-preview', [AiScenarioVoiceCallController::class, 'start'])
        ->name('ai-scenarios.voice-preview.start');
    Route::post('ai-scenarios/voice-preview/{attempt}/turns', [AiScenarioVoiceCallController::class, 'turn'])
        ->name('ai-scenarios.voice-preview.turn');
    Route::post('ai-scenarios/voice-preview/{attempt}/end', [AiScenarioVoiceCallController::class, 'end'])
        ->name('ai-scenarios.voice-preview.end');
});

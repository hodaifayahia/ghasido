<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\TestsController;
use Illuminate\Support\Facades\Route;

// Pre-test & Post-test builder (TEST-01..10, TSTM-01..05). Included from
// routes/admin.php inside the auth group.
Route::get('tests', [TestsController::class, 'index'])
    ->middleware(Permission::TestsView->middleware())
    ->name('tests');

Route::middleware(Permission::TestsManage->middleware())->group(function () {
    Route::post('tests', [TestsController::class, 'store'])->name('tests.store');
    Route::patch('tests/{test}', [TestsController::class, 'update'])->name('tests.update');
    Route::post('tests/{test}/publish', [TestsController::class, 'publish'])->name('tests.publish');
    Route::post('tests/{test}/questions', [TestsController::class, 'storeQuestion'])->name('tests.questions.store');
    // Bulk import from CSV or pasted rows (client request 2026-09-26).
    Route::get('tests/questions/import-template', [TestsController::class, 'importTemplate'])->name('tests.questions.import-template');
    Route::post('tests/{test}/questions/import', [TestsController::class, 'importQuestions'])->name('tests.questions.import');
    Route::patch('tests/{test}/questions/{placement}', [TestsController::class, 'updateQuestion'])->name('tests.questions.update');
    Route::patch('tests/{test}/questions/{placement}/media', [TestsController::class, 'updateQuestionMedia'])->name('tests.questions.media');
    Route::delete('tests/{test}/questions/{placement}', [TestsController::class, 'destroyQuestion'])->name('tests.questions.destroy');

    // AI question drafts, stored listening audio and draft release
    // (GEN-01, GEN-03, GEN-04, TTS-01, TTS-02, PERF-04; spec 0004).
    Route::post('tests/{test}/ai-questions', [TestsController::class, 'generateQuestions'])->name('tests.ai.generate');
    Route::post('tests/{test}/ai-questions/regenerate', [TestsController::class, 'regenerateQuestions'])->name('tests.ai.regenerate');
    Route::post('tests/{test}/audio', [TestsController::class, 'generateAudio'])->name('tests.audio.generate');
    Route::post('tests/{test}/questions/{placement}/audio', [TestsController::class, 'generateQuestionAudio'])->name('tests.questions.audio');
    Route::post('tests/{test}/questions/{placement}/release', [TestsController::class, 'releaseQuestion'])->name('tests.questions.release');
});

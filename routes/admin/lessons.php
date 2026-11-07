<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\Lessons\ActivityController;
use App\Http\Controllers\Admin\Lessons\AudioController;
use App\Http\Controllers\Admin\Lessons\BlockActivityController;
use App\Http\Controllers\Admin\Lessons\BlockController;
use App\Http\Controllers\Admin\Lessons\CourseController;
use App\Http\Controllers\Admin\Lessons\LessonController;
use App\Http\Controllers\Admin\Lessons\LessonCreateController;
use App\Http\Controllers\Admin\Lessons\LessonGenerationController;
use App\Http\Controllers\Admin\Lessons\LessonPreviewController;
use App\Http\Controllers\Admin\Lessons\LessonsContentController;
use App\Http\Controllers\Admin\Lessons\LexiconController;
use App\Http\Controllers\Admin\Lessons\MediaController;
use App\Http\Controllers\Admin\Lessons\UnitController;
use Illuminate\Support\Facades\Route;

// Lessons & Content — the CMS (CMS-01..06, BLD-01..03, BLD-07, GEN-01..05,
// TTS-01..03, MED-01..03, MED-07; spec 0003, Part D). Included from
// routes/admin.php inside the auth group. Every route carries the capability
// it needs; the policy inside each action is the authorization proper
// (ROLE-01, ROLE-02, SEC-01).
Route::middleware(Permission::LessonsView->middleware())->group(function () {
    Route::get('lessons-content', [LessonsContentController::class, 'index'])->name('lessons-content');
    Route::get('lessons/{lesson}/edit', [LessonsContentController::class, 'edit'])->name('lessons.edit');
    Route::get('lessons/{lesson}/preview/{block?}', [LessonPreviewController::class, 'show'])->name('lessons.preview');
    Route::post('lessons/{lesson}/preview/{block}/next', [LessonPreviewController::class, 'next'])->name('lessons.preview.next');
    Route::get('lexicon', [LexiconController::class, 'index'])->name('lexicon.index');
    Route::get('media', [MediaController::class, 'index'])->name('media.index');
});

Route::middleware(Permission::LessonsManage->middleware())->group(function () {
    Route::get('lessons-content/create', [LessonCreateController::class, 'create'])->name('lessons-content.create');
    Route::post('courses', [CourseController::class, 'store'])->name('courses.store');
    Route::patch('courses/{course}', [CourseController::class, 'update'])->name('courses.update');
    Route::post('courses/{course}/archive', [CourseController::class, 'archive'])->name('courses.archive');
    // Copy and delete a whole course or unit (client request 2026-10-05).
    Route::post('courses/{course}/duplicate', [CourseController::class, 'duplicate'])->name('courses.duplicate');
    Route::delete('courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');

    Route::post('units', [UnitController::class, 'store'])->name('units.store');
    Route::patch('units/{unit}', [UnitController::class, 'update'])->name('units.update');
    Route::post('units/{unit}/duplicate', [UnitController::class, 'duplicate'])->name('units.duplicate');
    Route::delete('units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');

    Route::post('lessons', [LessonController::class, 'store'])->name('lessons.store');
    Route::patch('lessons/{lesson}', [LessonController::class, 'update'])->name('lessons.update');
    Route::post('lessons/{lesson}/publish', [LessonController::class, 'publish'])->name('lessons.publish');
    Route::post('lessons/{lesson}/duplicate', [LessonController::class, 'duplicate'])->name('lessons.duplicate');
    // Move a lesson within its unit or to another unit or course (client
    // request 2026-11-07).
    Route::patch('lessons/{lesson}/move', [LessonController::class, 'move'])->name('lessons.move');
    Route::get('lessons/{lesson}/move-targets', [LessonController::class, 'moveTargets'])->name('lessons.move-targets');
    Route::post('lessons/{lesson}/archive', [LessonController::class, 'archive'])->name('lessons.archive');
    Route::delete('lessons/{lesson}', [LessonController::class, 'destroy'])->name('lessons.destroy');

    Route::post('lessons/{lesson}/blocks', [BlockController::class, 'store'])->name('blocks.store');
    Route::put('lessons/{lesson}/blocks/reorder', [BlockController::class, 'reorder'])->name('blocks.reorder');
    Route::patch('blocks/{block}', [BlockController::class, 'update'])->name('blocks.update');
    Route::post('blocks/{block}/duplicate', [BlockController::class, 'duplicate'])->name('blocks.duplicate');
    Route::post('blocks/{block}/toggle', [BlockController::class, 'toggle'])->name('blocks.toggle');
    Route::delete('blocks/{block}', [BlockController::class, 'destroy'])->name('blocks.destroy');

    Route::post('lexicon', [LexiconController::class, 'store'])->name('lexicon.store');
    Route::patch('lexicon/{item}', [LexiconController::class, 'update'])->name('lexicon.update');
    Route::post('lexicon/{item}/generate', [LexiconController::class, 'generate'])->name('lexicon.generate');
    Route::post('lexicon/{item}/apply-draft', [LexiconController::class, 'applyDraft'])->name('lexicon.apply-draft');
    Route::post('lexicon/{item}/audio', [LexiconController::class, 'audio'])->name('lexicon.audio');

    Route::post('activities', [ActivityController::class, 'store'])->name('activities.store');
    Route::patch('activities/{activity}', [ActivityController::class, 'update'])->name('activities.update');
    // The activities of a Practice block / the questions of a Quiz block
    // (BLD-03, PRAC-01..07): created and edited through activities.*.
    Route::put('blocks/{block}/activities/reorder', [BlockActivityController::class, 'reorder'])->name('blocks.activities.reorder');
    Route::delete('blocks/{block}/activities/{placement}', [BlockActivityController::class, 'destroy'])->scopeBindings()->name('blocks.activities.destroy');

    Route::post('audio/generate', [AudioController::class, 'generate'])->name('audio.generate');
    Route::post('audio/generate-all', [AudioController::class, 'generateAll'])->name('audio.generate-all');
    Route::post('audio/replace', [AudioController::class, 'replace'])->name('audio.replace');

    Route::post('media', [MediaController::class, 'store'])->name('media.store');

    // Generate with AI: whole lessons, whole courses and editor images
    // (GEN-01, GEN-03, GEN-04, PERF-04; spec 0004). JSON, polled by the page.
    Route::get('lesson-generations/courses', [LessonGenerationController::class, 'courses'])->name('lesson-generations.courses');
    Route::post('lesson-generations/images', [LessonGenerationController::class, 'image'])->name('lesson-generations.image');
    Route::post('lesson-generations', [LessonGenerationController::class, 'store'])->name('lesson-generations.store');
    // Fill one existing lesson from a brief (client request 2026-11-07).
    Route::post('lessons/{lesson}/ai-fill', [LessonGenerationController::class, 'fill'])->name('lessons.ai-fill');
    Route::get('lesson-generations/{generation}', [LessonGenerationController::class, 'show'])->name('lesson-generations.show');
    Route::post('lesson-generations/{generation}/retry', [LessonGenerationController::class, 'retry'])->name('lesson-generations.retry');
});

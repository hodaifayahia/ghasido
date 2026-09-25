<?php

use App\Enums\Role;
use App\Http\Controllers\Learn\ActivityController;
use App\Http\Controllers\Learn\CertificateController;
use App\Http\Controllers\Learn\FirstLoginController;
use App\Http\Controllers\Learn\HomeController;
use App\Http\Controllers\Learn\LessonsController;
use App\Http\Controllers\Learn\LessonStepController;
use App\Http\Controllers\Learn\MessagesController;
use App\Http\Controllers\Learn\PhrasebookController;
use App\Http\Controllers\Learn\ProgressController;
use App\Http\Controllers\Learn\PronunciationController;
use App\Http\Controllers\Learn\RecordingController;
use App\Http\Controllers\Learn\RoleplayController;
use App\Http\Controllers\Learn\TestController;
use App\Http\Controllers\Learn\TrainingDepartmentController;
use App\Http\Controllers\Learn\VoiceCallController;
use App\Http\Controllers\VoiceAgentLlmController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
 * Qwen as the voice agent's brain (spec 0004). Deepgram calls this from its
 * own servers, so there is no session and no CSRF token: the signed,
 * short-lived {token} bound to one in-progress voice call is the only
 * credential, verified by the controller. The Qwen key never leaves the
 * server (API-02, SEC-03).
 */
Route::post('voice-agent/llm/{token}/chat/completions', VoiceAgentLlmController::class)
    ->where('token', '[A-Za-z0-9_\-]+\.[a-f0-9]{64}')
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->middleware('throttle:120,1')
    ->name('voice-agent.llm');

/*
 * The employee area (spec 0003, Part E).
 *
 * Every route: signed in, holding the employee role (or Super Admin for
 * employee-preview in the CMS, or a Manager training as an employee — client
 * decision 2026-09-23), on a hotel that still allows access (AUTH-08,
 * SUB-05). Everything but the first-login pair and the manager's department
 * chooser also waits for the first-login screen (AUTH-04) and, for a manager,
 * for a chosen training department. Per-record authorization is the policies'
 * job inside the controllers (ROLE-02, SEC-01); a refusal is a 403.
 */
Route::middleware([
    'auth',
    'role:'.Role::Employee->value.'|'.Role::SuperAdmin->value.'|'.Role::Manager->value,
    'hotel.access',
])
    ->prefix('learn')
    ->name('learn.')
    ->group(function () {
        Route::get('first-login', [FirstLoginController::class, 'edit'])->name('first-login.edit');
        Route::post('first-login', [FirstLoginController::class, 'update'])->name('first-login.update');

        // The manager's department chooser sits outside the training.department
        // gate, so a manager with no choice yet can reach it (client decision
        // 2026-09-23).
        Route::get('training-department', [TrainingDepartmentController::class, 'edit'])->name('training-department.edit');
        Route::post('training-department', [TrainingDepartmentController::class, 'update'])->name('training-department.update');

        Route::middleware(['first-login', 'training.department'])->group(function () {
            Route::get('/', [HomeController::class, 'index'])->name('home');
            Route::get('post-test', [HomeController::class, 'postTest'])->name('post-test');

            Route::get('lessons', [LessonsController::class, 'index'])->name('lessons');
            Route::get('lessons/{lesson}', [LessonsController::class, 'show'])->name('lessons.show');

            // {block} is scoped to {lesson} and {placement} to {block}, so a
            // block or activity of another lesson is a 404 at the router.
            Route::scopeBindings()->group(function () {
                Route::get('lessons/{lesson}/steps/{block}', [LessonStepController::class, 'show'])->name('lessons.step');
                Route::post('lessons/{lesson}/steps/{block}/complete', [LessonStepController::class, 'complete'])->name('lessons.step.complete');
                Route::get('lessons/{lesson}/steps/{block}/activities/{placement}', [ActivityController::class, 'show'])->name('lessons.activity');
                Route::post('lessons/{lesson}/steps/{block}/activities/{placement}/answers', [ActivityController::class, 'answer'])->name('lessons.activity.answer');

                // Pronunciation check of a speaking step (spec 0006): JSON,
                // throttled; the daily cap lives in the controller.
                Route::post('lessons/{lesson}/steps/{block}/pronunciation', [PronunciationController::class, 'store'])
                    ->middleware('throttle:30,1')
                    ->name('pronunciation.store');
            });

            Route::get('pronunciation/{attempt}', [PronunciationController::class, 'show'])->name('pronunciation.show');

            Route::get('lessons/{lesson}/steps/{block}/roleplay/{scenario}', [RoleplayController::class, 'ready'])->name('roleplay.ready');
            Route::post('lessons/{lesson}/steps/{block}/roleplay/{scenario}/attempts', [RoleplayController::class, 'start'])->name('roleplay.start');
            Route::get('roleplay/attempts/{attempt}', [RoleplayController::class, 'show'])->name('roleplay.attempt');
            Route::post('roleplay/attempts/{attempt}/messages', [RoleplayController::class, 'message'])->name('roleplay.message');
            Route::post('roleplay/attempts/{attempt}/end', [RoleplayController::class, 'end'])->name('roleplay.end');
            Route::get('roleplay/attempts/{attempt}/feedback', [RoleplayController::class, 'feedback'])->name('roleplay.feedback');

            // Live spoken role-play through the Deepgram Voice Agent (spec
            // 0004). JSON endpoints: start returns a short-lived session,
            // turn stores one caption, end queues the evaluation.
            Route::post('lessons/{lesson}/steps/{block}/roleplay/{scenario}/voice', [VoiceCallController::class, 'start'])->name('roleplay.voice.start');
            Route::post('roleplay/attempts/{attempt}/voice/turns', [VoiceCallController::class, 'turn'])->name('roleplay.voice.turn');
            Route::post('roleplay/attempts/{attempt}/voice/end', [VoiceCallController::class, 'end'])->name('roleplay.voice.end');

            Route::post('tests/{test}/attempts', [TestController::class, 'start'])->name('tests.start');
            Route::get('tests/{test}/attempts/{attempt}/questions/{number}', [TestController::class, 'question'])->name('tests.question');
            Route::put('tests/{test}/attempts/{attempt}/questions/{number}', [TestController::class, 'answer'])->name('tests.answer');
            Route::post('tests/{test}/attempts/{attempt}/finish', [TestController::class, 'finish'])->name('tests.finish');
            Route::get('tests/{test}/attempts/{attempt}/result', [TestController::class, 'result'])->name('tests.result');

            Route::get('phrasebook', [PhrasebookController::class, 'index'])->name('phrasebook');
            Route::get('phrasebook/review', [PhrasebookController::class, 'review'])->name('phrasebook.review');
            Route::post('phrasebook/{item}/review', [PhrasebookController::class, 'recordReview'])->name('phrasebook.review.store');
            Route::post('phrasebook', [PhrasebookController::class, 'store'])->name('phrasebook.store');
            Route::delete('phrasebook/{item}', [PhrasebookController::class, 'destroy'])->name('phrasebook.destroy');

            Route::get('progress', [ProgressController::class, 'index'])->name('progress');

            Route::get('messages', [MessagesController::class, 'index'])->name('messages');
            Route::post('messages/{reminder}/read', [MessagesController::class, 'read'])->name('messages.read');

            Route::post('recordings', [RecordingController::class, 'store'])->name('recordings.store');

            Route::get('certificate', [CertificateController::class, 'index'])->name('certificate');
        });
    });

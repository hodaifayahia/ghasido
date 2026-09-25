<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\Reports\ReportExportController;
use App\Http\Controllers\Admin\Reports\ScoreOverrideController;
use App\Http\Controllers\Admin\ReportsExportController;
use Illuminate\Support\Facades\Route;

// Reports & Export (spec 0003, Part D). Included from routes/admin.php inside
// the auth group. Every route carries the permission it needs; the form
// request inside each action re-checks it and the tenant boundary
// (ROLE-01, ROLE-02, SEC-01).
Route::get('reports-export', ReportsExportController::class)
    ->middleware(Permission::ReportsView->middleware())
    ->name('reports-export');

// dataset=employees|answers|roleplay|lessons|comparison|anonymised,
// format=csv|xlsx|pdf, plus the page's filters. The anonymised dataset
// additionally requires reports.export_anonymised (checked by the request).
Route::get('reports-export/export', ReportExportController::class)
    ->middleware(Permission::ReportsExport->middleware())
    ->name('reports.export');

// Adjusting an AI score (AIE-05; spec 0005 §2.5): replace it with a reason,
// restore the machine's value, or ask the AI to grade again. The attempt
// policy's `overrideScore` ability re-checks the hotel boundary.
Route::middleware(Permission::ScoresOverride->middleware())
    ->prefix('reports-export')
    ->name('reports.')
    ->group(function (): void {
        Route::patch('answers/{attempt}/score', [ScoreOverrideController::class, 'updateAnswer'])->name('answers.score.update');
        Route::delete('answers/{attempt}/score', [ScoreOverrideController::class, 'restoreAnswer'])->name('answers.score.restore');
        Route::post('answers/{attempt}/reevaluate', [ScoreOverrideController::class, 'reevaluateAnswer'])->name('answers.reevaluate');
        Route::patch('roleplay/{roleplayAttempt}/score', [ScoreOverrideController::class, 'updateRoleplay'])->name('roleplay.score.update');
        Route::delete('roleplay/{roleplayAttempt}/score', [ScoreOverrideController::class, 'restoreRoleplay'])->name('roleplay.score.restore');
        Route::post('roleplay/{roleplayAttempt}/reevaluate', [ScoreOverrideController::class, 'reevaluateRoleplay'])->name('roleplay.reevaluate');
    });

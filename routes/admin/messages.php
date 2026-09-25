<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\Messages\AutomationRuleController;
use App\Http\Controllers\Admin\Messages\ReminderDraftController;
use App\Http\Controllers\Admin\Messages\ReminderSendController;
use App\Http\Controllers\Admin\Messages\ReminderTemplateController;
use App\Http\Controllers\Admin\MessagesRemindersController;
use Illuminate\Support\Facades\Route;

// Messages & Reminders (spec 0003, Part D). Included from routes/admin.php
// inside the auth group. Every route carries the permission it needs; the
// policy check inside each action is the authorization proper (ROLE-01,
// SEC-01). Templates and rules are written by the Super Admin only, which
// the policies decide: a manager holds messages.manage for sending (REM-07).
Route::middleware(Permission::MessagesView->middleware())->group(function () {
    Route::get('messages-reminders', MessagesRemindersController::class)
        ->name('messages-reminders');
    Route::get('messages-reminders/templates/preview', [ReminderTemplateController::class, 'preview'])
        ->name('messages-reminders.templates.preview');
});

Route::middleware(Permission::MessagesManage->middleware())->group(function () {
    Route::post('messages-reminders/send', [ReminderSendController::class, 'store'])
        ->name('messages-reminders.send');

    Route::post('messages-reminders/templates', [ReminderTemplateController::class, 'store'])
        ->name('messages-reminders.templates.store');
    Route::patch('messages-reminders/templates/{template}', [ReminderTemplateController::class, 'update'])
        ->name('messages-reminders.templates.update');

    // "Draft with AI" (spec 0005 §4.2): queued, then polled. Throttled so a
    // stuck button cannot spend the AI budget.
    Route::post('messages-reminders/templates/draft', [ReminderDraftController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('messages-reminders.templates.draft');
    Route::get('messages-reminders/templates/draft', [ReminderDraftController::class, 'show'])
        ->name('messages-reminders.templates.draft.show');

    Route::post('messages-reminders/automation-rules', [AutomationRuleController::class, 'store'])
        ->name('messages-reminders.rules.store');
    Route::patch('messages-reminders/automation-rules/{rule}', [AutomationRuleController::class, 'update'])
        ->name('messages-reminders.rules.update');
    Route::patch('messages-reminders/automation-rules/{rule}/toggle', [AutomationRuleController::class, 'toggle'])
        ->name('messages-reminders.rules.toggle');
});

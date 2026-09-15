<?php

use App\Http\Controllers\Admin\HotelsController;
use App\Http\Controllers\Admin\AiScenariosController;
use App\Http\Controllers\Admin\DepartmentsController;
use App\Http\Controllers\Admin\EmployeesController;
use App\Http\Controllers\Admin\LessonsContentController;
use App\Http\Controllers\Admin\MessagesRemindersController;
use App\Http\Controllers\Admin\ReportsExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('hotels', HotelsController::class)->name('hotels');
    Route::get('departments', DepartmentsController::class)->name('departments');
    Route::get('ai-scenarios', AiScenariosController::class)
        ->name('ai-scenarios');
    Route::get('employees', EmployeesController::class)->name('employees');
    Route::get('lessons-content', LessonsContentController::class)
        ->name('lessons-content');
    Route::get('messages-reminders', MessagesRemindersController::class)
        ->name('messages-reminders');
    Route::get('reports-export', ReportsExportController::class)
        ->name('reports-export');
});
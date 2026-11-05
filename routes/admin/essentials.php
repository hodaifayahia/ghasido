<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\Essentials\EssentialsStudioController;
use Illuminate\Support\Facades\Route;

// The English Essentials studio (client request 2026-11-05). Included from
// routes/admin.php inside the auth group. Every route carries the
// capability it needs; the form requests and policies inside each action
// are the authorization proper (ROLE-01, SEC-01). Topic show/hide, edit
// and delete, and lesson publish and delete, use the courses.* and
// lessons.* routes.
Route::get('english-essentials', [EssentialsStudioController::class, 'index'])
    ->middleware(Permission::LessonsView->middleware())
    ->name('essentials-studio');

Route::get('english-essentials/lessons/{lesson}/steps', [EssentialsStudioController::class, 'steps'])
    ->middleware(Permission::LessonsView->middleware())
    ->name('essentials-studio.lessons.steps');

Route::middleware(Permission::LessonsManage->middleware())->group(function () {
    Route::post('english-essentials/topics', [EssentialsStudioController::class, 'storeTopic'])->name('essentials-studio.topics.store');
    Route::put('english-essentials/topics/order', [EssentialsStudioController::class, 'reorderTopics'])->name('essentials-studio.topics.reorder');
    Route::post('english-essentials/{course}/lessons', [EssentialsStudioController::class, 'storeLesson'])->name('essentials-studio.lessons.store');
    Route::put('english-essentials/{course}/lessons/order', [EssentialsStudioController::class, 'reorderLessons'])->name('essentials-studio.lessons.reorder');
});

<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\ContactMessagesController;
use App\Http\Controllers\Admin\DeleteController;
use Illuminate\Support\Facades\Route;

// Contact Requests: messages from the public Contact Us page (user request
// 2026-09-26). Marking one read is `contact-messages.read` in settings.php.
Route::get('contact-messages', [ContactMessagesController::class, 'index'])
    ->middleware(Permission::LandingManage->middleware())
    ->name('contact-messages');

Route::delete('contact-messages/{contactMessage}', [DeleteController::class, 'contactMessage'])
    ->middleware(Permission::LandingManage->middleware())
    ->name('contact-messages.destroy');

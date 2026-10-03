<?php

use Illuminate\Support\Facades\Route;

/*
 * Every admin screen is guarded by the capability it needs, on the server
 * (ROLE-01, SEC-01, spec 0001). Lacking it is a 403, never a redirect and
 * never a 404, so a blocked user can tell a boundary from a broken link.
 *
 * The middleware argument comes from App\Enums\Permission, never a bare
 * string, so a typo fails static analysis instead of surfacing as a silent
 * 403 (spec 0001, invariant 5).
 *
 * One file per screen (spec 0003, Part D): each screen's routes live in
 * routes/admin/<screen>.php so the screens can be built independently.
 */
Route::middleware(['auth', 'hotel.access'])->group(function () {
    require __DIR__.'/admin/hotels.php';
    require __DIR__.'/admin/subscriptions.php';
    require __DIR__.'/admin/payments.php';
    require __DIR__.'/admin/departments.php';
    require __DIR__.'/admin/employees.php';
    require __DIR__.'/admin/lessons.php';
    require __DIR__.'/admin/ai-scenarios.php';
    require __DIR__.'/admin/tests.php';
    require __DIR__.'/admin/translations.php';
    require __DIR__.'/admin/tts.php';
    require __DIR__.'/admin/messages.php';
    require __DIR__.'/admin/reports.php';
    require __DIR__.'/admin/roles.php';
    require __DIR__.'/admin/users.php';
    require __DIR__.'/admin/inbox.php';
});

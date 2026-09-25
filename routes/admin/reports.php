<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\Reports\ReportExportController;
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

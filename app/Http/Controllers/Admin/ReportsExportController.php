<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\ReportsRequest;
use App\Services\Reports\ReportPage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Reports & Export screen (REP-01, REP-02, REP-04, TEST-10, DATA-01..05,
 * AIE-04, ADM-02; spec 0003 Part D).
 *
 * Read → authorize → delegate. The form request authorizes the read and
 * the tenant boundary; ReportPage builds every prop from the models.
 */
class ReportsExportController extends Controller
{
    public function __invoke(ReportsRequest $request): Response
    {
        return Inertia::render(
            'admin/ReportsExport',
            (new ReportPage($request->filters(), $request->viewer()))->build(),
        );
    }
}

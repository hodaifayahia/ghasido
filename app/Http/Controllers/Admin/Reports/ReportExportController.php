<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\ExportReportRequest;
use App\Services\Reports\ReportExporter;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Runs an export with the page's current filters (REP-03, REP-05..08,
 * SEC-06; spec 0003 Part D).
 *
 * CSV and XLSX download; PDF renders the print-styled page in the new tab
 * the button opened, where the person prints it (a PDF package is a
 * pending client decision, see HANDOFF.md).
 */
class ReportExportController extends Controller
{
    public function __invoke(ExportReportRequest $request): StreamedResponse|BinaryFileResponse|View
    {
        return (new ReportExporter($request->filters(), $request->viewer()))
            ->respond($request->dataset(), $request->exportFormat());
    }
}

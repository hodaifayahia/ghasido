<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Exports\EmployeesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Employees\ExportEmployeesRequest;
use App\Models\User;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export Employees to .xlsx or .csv with the current filters (REP-03,
 * REP-08, SEC-06; spec 0003 Part D, employees.export).
 */
class EmployeeExportController extends Controller
{
    public function download(ExportEmployeesRequest $request, EmployeesExport $export): StreamedResponse|BinaryFileResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return $export->download($request, $actor, $request->exportFormat());
    }
}

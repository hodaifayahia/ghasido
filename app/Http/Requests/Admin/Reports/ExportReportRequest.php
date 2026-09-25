<?php

namespace App\Http\Requests\Admin\Reports;

use App\Enums\Permission;
use App\Models\User;
use App\Services\Reports\Exporters\DatasetExport;
use App\Services\Reports\ReportFilters;
use Illuminate\Validation\Rule;

/**
 * One export run (REP-03, REP-07, REP-08, ROLE-02).
 *
 * `reports.export` opens every dataset but the anonymised research
 * export, which needs `reports.export_anonymised` on top; both are
 * refused with a 403, as is any parameter that reaches outside the
 * viewer's hotel.
 */
class ExportReportRequest extends ReportsRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || ! $user->can(Permission::ReportsExport->value)) {
            return false;
        }

        if ($this->query('dataset') === DatasetExport::DATASET_ANONYMISED
            && ! $user->can(Permission::ReportsExportAnonymised->value)) {
            return false;
        }

        return ReportFilters::viewerMayUse($this, $user);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'dataset' => ['required', Rule::in(DatasetExport::DATASETS)],
            'format' => ['required', Rule::in(DatasetExport::FORMATS)],
        ];
    }

    public function dataset(): string
    {
        return (string) $this->query('dataset');
    }

    public function exportFormat(): string
    {
        return (string) $this->query('format');
    }
}

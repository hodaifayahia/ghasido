<?php

namespace App\Services\Reports;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Reports\Exporters\AnonymisedExport;
use App\Services\Reports\Exporters\AnswersExport;
use App\Services\Reports\Exporters\ComparisonExport;
use App\Services\Reports\Exporters\DatasetExport;
use App\Services\Reports\Exporters\EmployeesExport;
use App\Services\Reports\Exporters\LessonProgressExport;
use App\Services\Reports\Exporters\RoleplayExport;
use App\Support\SimpleXlsxWriter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Runs one export in one format and leaves its trace (REP-03, REP-05,
 * REP-06, REP-08, SEC-06, ADM-03; spec 0003 Part D).
 *
 * CSV streams row by row through fputcsv; XLSX goes through the
 * dependency-free SimpleXlsxWriter into a temporary file that is deleted
 * after sending; PDF is the print-styled page the browser prints. Every
 * export writes one audit row naming the dataset, the format, the filters
 * and the row count BEFORE the bytes leave, so a download that is cut off
 * half way is still on record.
 */
final class ReportExporter
{
    public const AUDIT_ACTION = 'report.exported';

    public function __construct(
        private readonly ReportFilters $filters,
        private readonly User $viewer,
    ) {}

    public function respond(string $dataset, string $format): StreamedResponse|BinaryFileResponse|View
    {
        $export = $this->export($dataset);
        $rowCount = $export->count();

        AuditLog::record($this->viewer, self::AUDIT_ACTION, [
            'dataset' => $dataset,
            'format' => $format,
            'filters' => $this->filters->toQuery(),
            'range' => $this->filters->rangeLabel(),
            'row_count' => $rowCount,
        ]);

        return match ($format) {
            DatasetExport::FORMAT_CSV => $this->csv($export),
            DatasetExport::FORMAT_XLSX => $this->xlsx($export),
            DatasetExport::FORMAT_PDF => $this->print($export, $rowCount),
            default => throw new InvalidArgumentException("Unknown export format [{$format}]."),
        };
    }

    public function export(string $dataset): DatasetExport
    {
        $population = new ReportPopulation($this->filters);

        return match ($dataset) {
            DatasetExport::DATASET_EMPLOYEES => new EmployeesExport($this->filters, $this->viewer, $population),
            DatasetExport::DATASET_ANSWERS => new AnswersExport($this->filters, $this->viewer, $population),
            DatasetExport::DATASET_ROLEPLAY => new RoleplayExport($this->filters, $this->viewer, $population),
            DatasetExport::DATASET_LESSONS => new LessonProgressExport($this->filters, $this->viewer, $population),
            DatasetExport::DATASET_COMPARISON => new ComparisonExport($this->filters, $this->viewer, $population),
            DatasetExport::DATASET_ANONYMISED => new AnonymisedExport($this->filters, $this->viewer, $population),
            default => throw new InvalidArgumentException("Unknown export dataset [{$dataset}]."),
        };
    }

    private function csv(DatasetExport $export): StreamedResponse
    {
        return response()->streamDownload(function () use ($export): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            // A byte-order mark so Excel opens the UTF-8 (Arabic answers) correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $export->headers(), ',', '"', '\\', "\r\n");

            foreach ($export->rows() as $row) {
                fputcsv($out, DatasetExport::cells($row), ',', '"', '\\', "\r\n");
            }

            fclose($out);
        }, $export->fileName('csv'), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function xlsx(DatasetExport $export): BinaryFileResponse
    {
        $path = SimpleXlsxWriter::fromRows($export->headers(), $export->rows(), $export->title());

        return response()
            ->download($path, $export->fileName('xlsx'), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    private function print(DatasetExport $export, int $rowCount): View
    {
        $population = new ReportPopulation($this->filters);
        $aggregates = new ReportAggregates($this->filters, $population);

        $rows = [];

        foreach ($export->rows() as $row) {
            $rows[] = DatasetExport::cells($row);
        }

        return view('reports.print', [
            'title' => $export->title(),
            'generatedAt' => Date::now(),
            'generatedBy' => $this->viewer->name,
            'rangeLabel' => $this->filters->rangeLabel(),
            'filterSummary' => $this->filterSummary(),
            'stats' => $aggregates->stats(),
            'headers' => $export->headers(),
            'rows' => $rows,
            'rowCount' => $rowCount,
        ]);
    }

    /**
     * The filters in words, for the print header.
     *
     * @return list<array{label: string, value: string}>
     */
    private function filterSummary(): array
    {
        $filters = $this->filters;

        return [
            ['label' => __('Period'), 'value' => $filters->rangeLabel()],
            ['label' => __('Hotel'), 'value' => $filters->hotelId === null ? __('All Hotels') : ($this->name('hotels', $filters->hotelId) ?? '#'.$filters->hotelId)],
            ['label' => __('Department'), 'value' => $filters->departmentId === null ? __('All Departments') : ($this->name('departments', $filters->departmentId) ?? '#'.$filters->departmentId)],
            ['label' => __('Employee'), 'value' => $filters->employeeId === null ? __('All Employees') : ($this->name('users', $filters->employeeId) ?? '#'.$filters->employeeId)],
            ['label' => __('Activity type'), 'value' => $filters->activityType],
            ['label' => __('Completion status'), 'value' => $filters->completionStatus],
        ];
    }

    private function name(string $table, int $id): ?string
    {
        $name = DB::table($table)->where('id', $id)->value('name');

        return is_string($name) ? $name : null;
    }
}

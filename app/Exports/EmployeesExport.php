<?php

namespace App\Exports;

use App\Enums\TrainingStatus;
use App\Http\Resources\Employees\EmployeeRowResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Employees\EmployeeDirectory;
use App\Support\SimpleXlsxWriter;
use Generator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export Employees: the filtered directory as a .csv stream or a .xlsx file
 * (REP-03, REP-08, SEC-06; spec 0003 Part D, employees.export).
 *
 * The rows are the directory's own query walked with lazyById(), so the
 * export is exactly the list on screen, in the same order, whatever its
 * size, and a manager's export never leaves their hotel. No transcript or
 * recording is anywhere near this dataset (REP-08).
 *
 * Every export writes one audit row against the actor with the filters,
 * the format and the row count.
 */
class EmployeesExport
{
    public const FORMATS = ['csv', 'xlsx'];

    /**
     * @var list<string>
     */
    private const HEADERS = [
        '#',
        'Name',
        'Username',
        'Hotel',
        'Department',
        'Email',
        'Progress %',
        'Status',
        'Last Login',
        'Last Activity',
        'Pre-test %',
        'Post-test %',
        'Reminder consent',
        'Participant code',
    ];

    public function __construct(private readonly EmployeeDirectory $directory) {}

    public function download(Request $request, User $actor, string $format): StreamedResponse|BinaryFileResponse
    {
        $filters = $this->directory->filtersFrom($request, $actor);
        $query = $this->directory->query($actor, $filters);
        $name = 'employees-'.Date::now()->format('Y-m-d').'.'.$format;
        $count = 0;

        $rows = function () use ($query, $request, &$count): Generator {
            $rank = 0;

            foreach ($query->lazyById(200, 'users.id', 'id') as $user) {
                $rank++;
                $count++;

                yield self::row((new EmployeeRowResource($user, $rank))->resolve($request));
            }
        };

        if ($format === 'xlsx') {
            $path = SimpleXlsxWriter::fromRows(self::HEADERS, $rows(), 'Employees');
            $this->audit($actor, $format, $filters, $count);

            return response()->download($path, $name, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend();
        }

        return response()->streamDownload(function () use ($rows, $actor, $format, $filters, &$count): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            // A BOM so Excel reads the UTF-8 names correctly.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, self::HEADERS);

            foreach ($rows() as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);

            $this->audit($actor, $format, $filters, $count);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    private static function row(array $row): array
    {
        return [
            $row['rank'],
            $row['name'],
            $row['username'],
            $row['hotel'],
            $row['department'],
            $row['emailAddress'] ?? '',
            $row['progress'],
            $row['status'],
            $row['lastLogin'] === '-' ? '' : $row['lastLogin'],
            $row['lastActivity'] === '-' ? '' : $row['lastActivity'],
            $row['preTestScore'] ?? '',
            $row['postTestScore'] ?? '',
            $row['emailConsent'] ? 'yes' : 'no',
            $row['participantCode'] ?? '',
        ];
    }

    /**
     * @param  array{search: string, hotel: int|null, department: int|null, status: TrainingStatus|null}  $filters
     */
    private function audit(User $actor, string $format, array $filters, int $rows): void
    {
        AuditLog::record($actor, 'employees.exported', [
            'format' => $format,
            'rows' => $rows,
            'filters' => [
                'search' => $filters['search'],
                'hotel' => $filters['hotel'],
                'department' => $filters['department'],
                'status' => $filters['status']?->value,
            ],
        ]);
    }
}

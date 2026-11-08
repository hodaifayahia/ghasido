<?php

namespace App\Services\Reports\Exporters;

use App\Models\User;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportPopulation;
use DateTimeInterface;
use Illuminate\Support\Facades\Date;

/**
 * One exportable dataset (REP-03..08, spec 0003 Part D).
 *
 * A dataset knows its headers and streams its rows through a generator,
 * so the CSV writer, the XLSX writer and the print page share one source
 * and memory does not grow with the row count. The population and range
 * are the page's own filters, so an export is exactly what was on screen.
 */
abstract class DatasetExport
{
    public const DATASET_EMPLOYEES = 'employees';

    public const DATASET_ANSWERS = 'answers';

    public const DATASET_ROLEPLAY = 'roleplay';

    public const DATASET_LESSONS = 'lessons';

    public const DATASET_COMPARISON = 'comparison';

    public const DATASET_ANONYMISED = 'anonymised';

    /** @var list<string> */
    public const DATASETS = [
        self::DATASET_EMPLOYEES,
        self::DATASET_ANSWERS,
        self::DATASET_ROLEPLAY,
        self::DATASET_LESSONS,
        self::DATASET_COMPARISON,
        self::DATASET_ANONYMISED,
    ];

    public const FORMAT_CSV = 'csv';

    public const FORMAT_XLSX = 'xlsx';

    public const FORMAT_PDF = 'pdf';

    /** @var list<string> */
    public const FORMATS = [self::FORMAT_CSV, self::FORMAT_XLSX, self::FORMAT_PDF];

    public const CHUNK = 200;

    public function __construct(
        protected readonly ReportFilters $filters,
        protected readonly User $viewer,
        protected readonly ReportPopulation $population,
    ) {}

    abstract public function key(): string;

    abstract public function title(): string;

    /**
     * @return list<string>
     */
    abstract public function headers(): array;

    /**
     * @return iterable<int, list<mixed>>
     */
    abstract public function rows(): iterable;

    abstract public function count(): int;

    public function fileName(string $extension): string
    {
        return 'ghasido-'.$this->key().'-'.Date::now()->format('Ymd-His').'.'.$extension;
    }

    /**
     * A cell value fit for fputcsv: scalars as they are, dates as ISO
     * strings, arrays as JSON, null as an empty string.
     */
    public static function cell(mixed $value): string|int|float
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value)) {
            return $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '' : $encoded;
    }

    /**
     * @param  list<mixed>  $row
     * @return list<string|int|float>
     */
    public static function cells(array $row): array
    {
        return array_map(static fn (mixed $value): string|int|float => self::cell($value), $row);
    }

    /**
     * Cells for a CSV file, with formula injection neutralised (security
     * audit 2026-11-08): learner free text that starts with =, +, -, @, a
     * tab or a carriage return would run as a formula when the file is
     * opened in Excel or Sheets, so it gets a leading apostrophe (OWASP
     * CSV injection). Numbers stay numbers; the stored answers are not
     * changed (DATA-03). XLSX needs none of this: its text cells are
     * inline strings, never evaluated.
     *
     * @param  list<mixed>  $row
     * @return list<string|int|float>
     */
    public static function csvCells(array $row): array
    {
        return array_map(static function (mixed $value): string|int|float {
            $cell = self::cell($value);

            if (is_string($cell) && $cell !== '' && in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'".$cell;
            }

            return $cell;
        }, $row);
    }

    protected static function decimal(?string $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}

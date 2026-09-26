<?php

namespace App\Console\Commands;

use App\Services\Hotels\SqlFragment;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Copy every row of the SQLite database into a migrated MySQL database
 * (client request 2026-09-26: move production from SQLite to MySQL).
 *
 * The MySQL schema comes from the migrations (`php artisan migrate
 * --database=mysql`), never from SQLite, so both databases must be on the
 * same migrations; the command checks that first. It then checks the data
 * against MySQL's stricter rules — text longer than its column (SQLite
 * ignores lengths) and invalid JSON — and stops before writing anything if a
 * row would not fit. Rows are copied table by table with their ids, and the
 * row counts of every table are compared at the end.
 *
 * Nothing is deleted from SQLite. `--fresh` empties the MySQL tables first,
 * so the command can be run again.
 */
final class CopySqliteToMysql extends Command
{
    protected $signature = 'db:copy-sqlite-to-mysql
        {--from=sqlite : The source connection}
        {--sqlite-path= : The SQLite file to read (default: database/database.sqlite)}
        {--to=mysql : The target connection}
        {--fresh : Empty the target tables before copying}
        {--dry-run : Only run the checks and show what would be copied}
        {--chunk=500 : Rows per insert}';

    protected $description = 'Copy all data from the SQLite database into a migrated MySQL database';

    /** Tables that belong to the framework's own state, not the data. */
    private const array SKIP = ['migrations', 'sqlite_sequence', 'cache', 'cache_locks', 'sessions'];

    public function handle(): int
    {
        // Both connections read DB_DATABASE, so once .env points at MySQL the
        // SQLite connection would too: name the file explicitly.
        $path = (string) ($this->option('sqlite-path') ?: database_path('database.sqlite'));

        if (! is_file($path)) {
            $this->error('SQLite file not found: '.$path);

            return self::FAILURE;
        }

        config(['database.connections.'.$this->option('from').'.database' => $path]);
        DB::purge((string) $this->option('from'));

        $from = DB::connection((string) $this->option('from'));
        $to = DB::connection((string) $this->option('to'));

        if ($from->getDriverName() !== 'sqlite' || ! in_array($to->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->error('The source must be a SQLite connection and the target a MySQL one.');

            return self::FAILURE;
        }

        $this->info(sprintf('From: %s (%s)', $from->getName(), $from->getDatabaseName()));
        $this->info(sprintf('To:   %s (%s)', $to->getName(), $to->getDatabaseName()));

        if (! $this->sameMigrations($from, $to)) {
            return self::FAILURE;
        }

        $tables = $this->tables($from, $to);

        if (! $this->option('fresh') && ! $this->option('dry-run')) {
            $busy = array_filter($tables, fn (string $table): bool => $to->table($table)->exists());

            if ($busy !== []) {
                $this->error('These MySQL tables already hold rows: '.implode(', ', $busy));
                $this->line('Run again with --fresh to empty them first.');

                return self::FAILURE;
            }
        }

        $problems = $this->preflight($from, $to, $tables);

        if ($problems !== []) {
            $this->error('Some rows would not fit in MySQL. Nothing was copied:');

            foreach ($problems as $problem) {
                $this->line('  - '.$problem);
            }

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->table(['Table', 'Rows'], array_map(
                fn (string $table): array => [$table, $from->table($table)->count()],
                $tables,
            ));
            $this->info('Checks passed. Dry run: nothing was copied.');

            return self::SUCCESS;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $to->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tables as $table) {
                if ($this->option('fresh')) {
                    $to->table($table)->truncate();
                }

                $this->copyTable($from, $to, $table, $chunk);
            }
        } catch (Throwable $e) {
            $this->error('Copy stopped: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            $to->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        return $this->verify($from, $to, $tables) ? self::SUCCESS : self::FAILURE;
    }

    private function sameMigrations(Connection $from, Connection $to): bool
    {
        if (! Schema::connection($to->getName())->hasTable('migrations')) {
            $this->error('The MySQL database has no tables yet. Run: php artisan migrate --database='.$to->getName().' --force');

            return false;
        }

        $source = $from->table('migrations')->pluck('migration')->sort()->values()->all();
        $target = $to->table('migrations')->pluck('migration')->sort()->values()->all();
        $missing = array_diff($source, $target);
        $extra = array_diff($target, $source);

        if ($missing !== [] || $extra !== []) {
            $this->error('The two databases are not on the same migrations.');

            foreach ($missing as $name) {
                $this->line('  missing in MySQL: '.$name);
            }

            foreach ($extra as $name) {
                $this->line('  only in MySQL:    '.$name);
            }

            $this->line('Run php artisan migrate on both first, from the same code.');

            return false;
        }

        return true;
    }

    /**
     * Tables present in both databases, parents before children where the
     * schema says so (foreign keys are off during the copy anyway).
     *
     * @return list<string>
     */
    private function tables(Connection $from, Connection $to): array
    {
        $source = array_map(fn (array $t): string => (string) $t['name'], Schema::connection($from->getName())->getTables());
        $target = array_map(fn (array $t): string => (string) $t['name'], Schema::connection($to->getName())->getTables());

        $onlySource = array_diff($source, $target, self::SKIP);

        if ($onlySource !== []) {
            $this->warn('Only in SQLite, not copied: '.implode(', ', $onlySource));
        }

        return array_values(array_filter(
            array_intersect($source, $target),
            fn (string $table): bool => ! in_array($table, self::SKIP, true),
        ));
    }

    /**
     * Every value MySQL would refuse: text longer than its column, invalid
     * JSON in a JSON column.
     *
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function preflight(Connection $from, Connection $to, array $tables): array
    {
        $problems = [];

        foreach ($tables as $table) {
            foreach (Schema::connection($to->getName())->getColumns($table) as $column) {
                $name = (string) $column['name'];
                $type = strtolower((string) $column['type']);

                if (! Schema::connection($from->getName())->hasColumn($table, $name)) {
                    continue;
                }

                if (preg_match('/^(?:var)?char\((\d+)\)/', $type, $m) === 1) {
                    $limit = (int) $m[1];
                    $too = $from->table($table)
                        ->whereRaw(new SqlFragment('length('.$this->quote($name).') > ?'), [$limit])
                        ->count();

                    if ($too > 0) {
                        $problems[] = sprintf('%s.%s: %d value(s) longer than %d characters', $table, $name, $too, $limit);
                    }
                }

                if ($type === 'text' || $type === 'tinytext') {
                    $limit = $type === 'text' ? 65535 : 255;
                    $too = $from->table($table)
                        ->whereRaw(new SqlFragment('length(cast('.$this->quote($name).' as blob)) > ?'), [$limit])
                        ->count();

                    if ($too > 0) {
                        $problems[] = sprintf('%s.%s: %d value(s) longer than MySQL %s (%d bytes)', $table, $name, $too, strtoupper($type), $limit);
                    }
                }

                if ($type === 'json') {
                    $bad = $from->table($table)
                        ->whereNotNull($name)
                        ->whereRaw(new SqlFragment('json_valid('.$this->quote($name).') = 0'))
                        ->count();

                    if ($bad > 0) {
                        $problems[] = sprintf('%s.%s: %d value(s) are not valid JSON', $table, $name, $bad);
                    }
                }
            }
        }

        return $problems;
    }

    private function copyTable(Connection $from, Connection $to, string $table, int $chunk): void
    {
        $columns = array_values(array_intersect(
            Schema::connection($from->getName())->getColumnListing($table),
            Schema::connection($to->getName())->getColumnListing($table),
        ));
        $total = $from->table($table)->count();
        $copied = 0;
        $dates = [];

        foreach (Schema::connection($to->getName())->getColumns($table) as $column) {
            $type = strtolower((string) $column['type']);

            if (in_array($type, ['date', 'datetime', 'timestamp'], true) || str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp')) {
                $dates[(string) $column['name']] = $type === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s';
            }
        }
        $offset = 0;

        // rowid is SQLite's own stable row order, so paging never skips or
        // repeats a row, whatever the table's key looks like.
        while (true) {
            $rows = $from->table($table)
                ->select($columns)
                ->orderByRaw('rowid')
                ->offset($offset)
                ->limit($chunk)
                ->get()
                ->map(fn (object $row): array => $this->normaliseDates((array) $row, $dates))
                ->all();

            if ($rows === []) {
                break;
            }

            $to->table($table)->insert($rows);
            $copied += count($rows);
            $offset += $chunk;
        }

        $this->line(sprintf('  %-40s %8d rows', $table, $copied));

        if ($copied !== $total) {
            throw new \RuntimeException(sprintf('%s: read %d of %d rows', $table, $copied, $total));
        }
    }

    /**
     * @param  list<string>  $tables
     */
    private function verify(Connection $from, Connection $to, array $tables): bool
    {
        $rows = [];
        $ok = true;

        foreach ($tables as $table) {
            $a = $from->table($table)->count();
            $b = $to->table($table)->count();
            $ok = $ok && $a === $b;
            $rows[] = [$table, $a, $b, $a === $b ? 'OK' : 'DIFFERENT'];
        }

        $this->table(['Table', 'SQLite', 'MySQL', ''], $rows);

        if ($ok) {
            $this->info(sprintf('Done: %d tables copied, every row count matches.', count($tables)));
        } else {
            $this->error('Some row counts differ; do not switch to MySQL yet.');
        }

        return $ok;
    }

    /**
     * SQLite keeps whatever date text it was given; MySQL wants
     * `Y-m-d H:i:s` (or `Y-m-d` for a DATE). Same instant, stricter shape.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $dates
     * @return array<string, mixed>
     */
    private function normaliseDates(array $row, array $dates): array
    {
        foreach ($dates as $column => $format) {
            $value = $row[$column] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            $canonical = $format === 'Y-m-d' ? '/^\d{4}-\d{2}-\d{2}$/' : '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';

            if (preg_match($canonical, $value) === 1) {
                continue;
            }

            $row[$column] = Carbon::parse($value)
                ->setTimezone((string) config('app.timezone', 'UTC'))
                ->format($format);
        }

        return $row;
    }

    private function quote(string $column): string
    {
        return '"'.str_replace('"', '""', $column).'"';
    }
}

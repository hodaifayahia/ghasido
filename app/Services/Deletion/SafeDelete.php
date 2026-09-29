<?php

namespace App\Services\Deletion;

use App\Models\AuditLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Safe delete for the admin tables (owner decision 2026-09-27): a row is
 * deleted for good only when nothing that matters depends on it. Otherwise
 * the delete is refused with one sentence naming what still depends on it
 * and what to do instead, so a mistaken click can never remove learner or
 * research data (DATA-10, SEC-06).
 *
 * Callers count the dependants that must block and pass them as
 * `['employee' => 12, 'test answer' => 40]`; zero counts are ignored.
 * A successful delete writes one audit row with a snapshot of the row.
 */
final class SafeDelete
{
    /**
     * @param  array<string, int>  $blockers  singular label => count
     * @param  (Closure(): void)|null  $cleanup  removes the row's own configuration first (pivots, quotas…)
     *
     * @throws ValidationException when anything blocks the delete
     */
    public static function run(
        Model $model,
        string $name,
        array $blockers,
        string $instead,
        string $action,
        ?Closure $cleanup = null,
        bool $person = false,
    ): void {
        self::refuseIfBlocked($name, $blockers, $instead, $person);

        DB::transaction(function () use ($model, $action, $cleanup): void {
            AuditLog::record($model, $action, ['deleted' => self::snapshot($model)]);

            if ($cleanup !== null) {
                $cleanup();
            }

            $model->delete();
        });
    }

    /**
     * @param  array<string, int>  $blockers
     *
     * @throws ValidationException
     */
    public static function refuseIfBlocked(string $name, array $blockers, string $instead, bool $person = false): void
    {
        $parts = [];

        foreach ($blockers as $label => $count) {
            if ($count > 0) {
                $parts[] = $count.' '.self::label($label, $count);
            }
        }

        if ($parts === []) {
            return;
        }

        $list = count($parts) === 1
            ? $parts[0]
            : implode(', ', array_slice($parts, 0, -1)).' '.__('and').' '.$parts[array_key_last($parts)];

        throw ValidationException::withMessages([
            'delete' => trim(($person
                ? __(':name cannot be deleted because they still have :list.', ['name' => $name, 'list' => $list])
                : __(':name cannot be deleted because it still has :list.', ['name' => $name, 'list' => $list])).' '.$instead),
        ]);
    }

    /**
     * The row as it was, minus secrets, for the audit trail.
     *
     * @return array<string, mixed>
     */
    private static function snapshot(Model $model): array
    {
        return array_diff_key($model->attributesToArray(), array_flip([
            'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        ]));
    }

    /**
     * "employee" / "employees", translated. A label whose plural is not just
     * "+s" is written `singular|plural`.
     */
    private static function label(string $label, int $count): string
    {
        if (str_contains($label, '|')) {
            [$one, $many] = explode('|', $label, 2);

            return __($count === 1 ? $one : $many);
        }

        return __($count === 1 ? $label : self::plural($label));
    }

    private static function plural(string $label): string
    {
        return match (true) {
            str_ends_with($label, 'y') && ! str_ends_with($label, 'ay') => substr($label, 0, -1).'ies',
            str_ends_with($label, 's'), str_ends_with($label, 'sh'), str_ends_with($label, 'ch') => $label.'es',
            default => $label.'s',
        };
    }
}

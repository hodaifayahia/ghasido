<?php

namespace App\Services\Reports;

use App\Enums\TestAttemptStatus;
use App\Enums\TestType;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Pre-test versus Post-test per employee per skill (TEST-10, REP-04).
 *
 * Reads each employee's latest submitted sitting of each type within the
 * range and lines up the `breakdown` the sitting stored per skill label
 * (correct / total / ungraded), one row per employee and skill. The table
 * tab and the export both read this, so they can never disagree.
 */
final class ComparisonBuilder
{
    public function __construct(
        private readonly ReportFilters $filters,
        private readonly ReportPopulation $population,
    ) {}

    /**
     * @return list<array{id: string, userId: int, participantCode: string|null, employee: string, username: string|null, hotel: string|null, department: string, skill: string, preCorrect: int|null, preTotal: int|null, prePercent: int|null, postCorrect: int|null, postTotal: int|null, postPercent: int|null, delta: int|null}>
     */
    public function rows(bool $searched = true): array
    {
        $members = ($searched ? $this->population->searched() : $this->population->query())
            ->with(['department:id,name', 'hotel:id,name'])
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->get(['users.id', 'users.name', 'users.username', 'users.participant_code', 'users.hotel_id', 'users.department_id']);

        if ($members->isEmpty()) {
            return [];
        }

        $latest = $this->latestSittings($members->map(fn (User $user): int => $user->id)->all());
        $rows = [];

        foreach ($members as $user) {
            $pre = $latest[$user->id][TestType::Pre->value] ?? null;
            $post = $latest[$user->id][TestType::Post->value] ?? null;

            if ($pre === null && $post === null) {
                continue;
            }

            $preSkills = self::skills($pre);
            $postSkills = self::skills($post);
            $labels = array_values(array_unique([...array_keys($preSkills), ...array_keys($postSkills)]));

            foreach ($labels as $skill) {
                $preFigures = $preSkills[$skill] ?? null;
                $postFigures = $postSkills[$skill] ?? null;
                $prePercent = self::percent($preFigures);
                $postPercent = self::percent($postFigures);

                $rows[] = [
                    'id' => $user->id.':'.$skill,
                    'userId' => $user->id,
                    'participantCode' => $user->participant_code,
                    'employee' => $user->name,
                    'username' => $user->username,
                    'hotel' => $user->hotel?->name,
                    'department' => $user->department->name ?? '—',
                    'skill' => $skill,
                    'preCorrect' => $preFigures['correct'] ?? null,
                    'preTotal' => $preFigures['total'] ?? null,
                    'prePercent' => $prePercent,
                    'postCorrect' => $postFigures['correct'] ?? null,
                    'postTotal' => $postFigures['total'] ?? null,
                    'postPercent' => $postPercent,
                    'delta' => $prePercent !== null && $postPercent !== null ? $postPercent - $prePercent : null,
                ];
            }
        }

        return $rows;
    }

    /**
     * The latest submitted sitting per employee and test type in the range.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, array<string, TestAttempt>>
     */
    private function latestSittings(array $userIds): array
    {
        $query = TestAttempt::query()
            ->with('test:id,type,title')
            ->whereIn('test_attempts.user_id', $userIds)
            ->where('test_attempts.status', TestAttemptStatus::Submitted->value)
            ->whereHas('test', fn (Builder $test) => $test->whereIn('type', [TestType::Pre->value, TestType::Post->value]))
            ->orderBy('test_attempts.submitted_at')
            ->orderBy('test_attempts.id');

        $this->filters->withinRange($query, 'test_attempts.submitted_at');

        $latest = [];

        // Ascending order, so the last write per (user, type) is the latest.
        foreach ($query->get() as $attempt) {
            $type = $attempt->test?->type;

            if ($type === null) {
                continue;
            }

            $latest[$attempt->user_id][$type->value] = $attempt;
        }

        return $latest;
    }

    /**
     * @return array<string, array{correct: int, total: int, ungraded: int}>
     */
    private static function skills(?TestAttempt $attempt): array
    {
        if ($attempt === null) {
            return [];
        }

        $skills = [];

        foreach ($attempt->breakdown ?? [] as $label => $figures) {
            if (! is_array($figures)) {
                continue;
            }

            $skills[(string) $label] = [
                'correct' => (int) ($figures['correct'] ?? 0),
                'total' => (int) ($figures['total'] ?? 0),
                'ungraded' => (int) ($figures['ungraded'] ?? 0),
            ];
        }

        return $skills;
    }

    /**
     * @param  array{correct: int, total: int, ungraded: int}|null  $figures
     */
    private static function percent(?array $figures): ?int
    {
        if ($figures === null || $figures['total'] <= 0) {
            return null;
        }

        return (int) round($figures['correct'] / $figures['total'] * 100);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public static function collect(array $rows): Collection
    {
        return new Collection($rows);
    }
}

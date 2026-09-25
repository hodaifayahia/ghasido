<?php

namespace App\Services\Learning;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * The learner's practice streak (spec 0005 §3.2): how many days in a row
 * they have learned something, read from the rows every step already writes
 * (PROG-01), so nothing new is stored and nothing can drift.
 *
 * A day counts when the learner finished a lesson step, answered a practice
 * or test question, held a role-play, or reviewed a phrasebook card. The
 * streak survives until the end of the day after the last active day, so a
 * learner who practised yesterday still sees it, marked "practise today".
 * Timestamps are bucketed in PHP in the app timezone, so the same code runs
 * on SQLite and MySQL.
 */
final class StreakService
{
    /** How far back the streak is counted. */
    public const WINDOW_DAYS = 120;

    /**
     * @return array{current: int, best: int, activeToday: bool, week: list<array{date: string, label: string, active: bool, today: bool}>}
     */
    public function summary(User $user): array
    {
        $today = CarbonImmutable::instance(Date::now())->startOfDay();
        $days = $this->activeDays($user, $today->subDays(self::WINDOW_DAYS));

        $activeToday = isset($days[$today->toDateString()]);
        $cursor = $activeToday ? $today : $today->subDay();
        $current = 0;

        while (isset($days[$cursor->toDateString()])) {
            $current++;
            $cursor = $cursor->subDay();
        }

        return [
            'current' => $current,
            'best' => max($current, $this->longestRun(array_keys($days))),
            'activeToday' => $activeToday,
            'week' => $this->week($today, $days),
        ];
    }

    /**
     * The distinct active dates since `$from`, keyed by Y-m-d.
     *
     * @return array<string, true>
     */
    public function activeDays(User $user, CarbonInterface $from): array
    {
        $sources = [
            ['block_completions', 'completed_at', null],
            ['attempts', 'submitted_at', null],
            ['test_attempts', 'submitted_at', null],
            ['roleplay_attempts', 'started_at', 'is_preview'],
            ['phrasebook_items', 'last_reviewed_at', null],
        ];

        $days = [];

        foreach ($sources as [$table, $column, $previewFlag]) {
            $query = DB::table($table)
                ->where('user_id', $user->id)
                ->where($column, '>=', $from)
                ->whereNotNull($column);

            if ($previewFlag !== null) {
                $query->where($previewFlag, false);
            }

            foreach ($query->pluck($column) as $timestamp) {
                if (is_string($timestamp) && $timestamp !== '') {
                    $days[CarbonImmutable::parse($timestamp)->toDateString()] = true;
                }
            }
        }

        return $days;
    }

    /**
     * @param  list<string>  $dates  Y-m-d
     */
    private function longestRun(array $dates): int
    {
        sort($dates);
        $best = 0;
        $run = 0;
        $previous = null;

        foreach ($dates as $date) {
            $day = CarbonImmutable::parse($date);
            $run = $previous !== null && $previous->addDay()->isSameDay($day) ? $run + 1 : 1;
            $best = max($best, $run);
            $previous = $day;
        }

        return $best;
    }

    /**
     * The last seven days, oldest first, for the week strip.
     *
     * @param  array<string, true>  $days
     * @return list<array{date: string, label: string, active: bool, today: bool}>
     */
    private function week(CarbonImmutable $today, array $days): array
    {
        $week = [];

        for ($offset = 6; $offset >= 0; $offset--) {
            $day = $today->subDays($offset);
            $week[] = [
                'date' => $day->toDateString(),
                'label' => $day->format('D'),
                'active' => isset($days[$day->toDateString()]),
                'today' => $offset === 0,
            ];
        }

        return $week;
    }
}

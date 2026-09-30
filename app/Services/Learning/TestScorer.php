<?php

namespace App\Services\Learning;

use App\Enums\ActivityType;
use App\Enums\EnglishLevel;
use App\Enums\GenerationStatus;
use App\Enums\TestAttemptStatus;
use App\Enums\TestType;
use App\Jobs\AssessSpokenPronunciation;
use App\Jobs\EvaluateWrittenAnswer;
use App\Jobs\TranscribeAndEvaluateSpokenAnswer;
use App\Models\Activity;
use App\Models\Attempt;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Platform\PlatformSettings;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Scores a finished sitting (TEST-06, TEST-09, DATA-01, DATA-11; spec 0003
 * Part E, B.6).
 *
 * Every question is scored against the version the answer referred to, the
 * per-answer row is completed (score / max / correctness), and the sitting
 * keeps only the totals and a per-skill breakdown. Speaking and writing are
 * not auto-scored here — they carry their own AI evaluation — so they are
 * left out of the automatic total.
 */
class TestScorer
{
    public function __construct(
        private readonly ActivityPresenter $presenter,
        private readonly ActivityScorer $scorer,
    ) {}

    public function finish(TestAttempt $attempt, TestAttemptStatus $status = TestAttemptStatus::Submitted): void
    {
        /** @var list<array{id: int, type: ActivityType, pronunciation: bool}> $toEvaluate */
        $toEvaluate = [];

        DB::transaction(function () use ($attempt, $status, &$toEvaluate): void {
            $test = $attempt->test()->firstOrFail();
            $answers = $attempt->answers()->get()->keyBy('activity_id');

            $totalScore = 0.0;
            $totalMax = 0.0;
            /** @var array<string, array{score: float, max: float, count: int}> $breakdown */
            $breakdown = [];

            foreach ($test->learnerQuestions() as $placement) {
                /** @var Activity $activity */
                $activity = $placement->activity()->firstOrFail();
                $version = $this->presenter->currentVersion($activity);

                $row = $answers->get($activity->id);
                /** @var array<string, mixed> $raw */
                $raw = is_array($row?->raw_answer) ? $row->raw_answer : [];

                $result = $this->scorer->score($version, $raw);
                $columns = $result->toAttemptColumns();

                $row?->update($columns);

                if (! $result->isAutoScored()) {
                    // A spoken or written answer is judged by the AI after
                    // the sitting closes (TEST-07, TEST-08, AIE-01).
                    if ($row !== null && $this->needsEvaluation($activity->type, $row)) {
                        $row->forceFill(['ai_status' => GenerationStatus::Pending])->save();
                        $toEvaluate[] = [
                            'id' => $row->id,
                            'type' => $activity->type,
                            'pronunciation' => $activity->type === ActivityType::Speaking && AttemptRecorder::asksPronunciation($version->items(), $raw),
                        ];
                    }

                    continue;
                }

                $score = (float) ($columns['score'] ?? 0);
                $max = (float) $columns['max_score'];
                $totalScore += $score;
                $totalMax += $max;

                $skill = $activity->skill_label ?? 'General';
                $breakdown[$skill]['score'] = ($breakdown[$skill]['score'] ?? 0) + $score;
                $breakdown[$skill]['max'] = ($breakdown[$skill]['max'] ?? 0) + $max;
                $breakdown[$skill]['count'] = ($breakdown[$skill]['count'] ?? 0) + 1;
            }

            $attempt->update([
                'status' => $status,
                'submitted_at' => Date::now(),
                'score' => round($totalScore, 2),
                'max_score' => round($totalMax, 2),
                'breakdown' => $breakdown,
                'results_released_at' => Date::now(),
            ]);

            $user = $attempt->user()->firstOrFail();
            $user->forceFill([
                'training_started_at' => $user->training_started_at ?? Date::now(),
                'last_activity_at' => Date::now(),
                ...self::levelColumns($user, $test, $totalScore, $totalMax),
            ])->save();
        });

        // After the commit, so a sync queue (tests) or a fast worker always
        // sees the finished rows.
        // rescue(): an evaluation failure is reported and left as a `failed`
        // row, never an error on the learner's finish request (PROG-04).
        foreach ($toEvaluate as $pending) {
            // Void closures: a PendingDispatch sends on destruct, which must
            // happen inside rescue() for the error to be caught.
            rescue(function () use ($pending): void {
                if ($pending['pronunciation']) {
                    AssessSpokenPronunciation::dispatch($pending['id']);
                } elseif ($pending['type'] === ActivityType::Speaking) {
                    TranscribeAndEvaluateSpokenAnswer::dispatch($pending['id']);
                } else {
                    EvaluateWrittenAnswer::dispatch($pending['id']);
                }
            });
        }
    }

    /**
     * A Pre-test at or above the Super Admin's threshold suggests the next
     * level; the learner then chooses to move up or stay (client decision
     * 2026-09-30). The level itself is the learner's choice, so a test never
     * changes it. Advanced has no next level, and a sitting with nothing
     * auto-graded (only spoken or written items) suggests nothing.
     *
     * @return array{level_suggestion?: EnglishLevel|null}
     */
    private static function levelColumns(User $user, Test $test, float $score, float $max): array
    {
        if ($test->type !== TestType::Pre || $max <= 0 || $user->english_level === null) {
            return [];
        }

        $next = $user->english_level->next();
        $passed = $score / $max * 100 >= app(PlatformSettings::class)->levelUpFrom();

        return ['level_suggestion' => $passed ? $next : null];
    }

    /**
     * A spoken answer needs a recording; a written one needs some text. An
     * already-judged row is never re-sent (the job would spend budget twice).
     */
    private function needsEvaluation(ActivityType $type, Attempt $row): bool
    {
        if ($row->ai_status === GenerationStatus::Done || $row->ai_status === GenerationStatus::Running) {
            return false;
        }

        return match ($type) {
            ActivityType::Speaking => $row->response_media_id !== null,
            ActivityType::Writing => self::hasText($row->raw_answer ?? []),
            default => false,
        };
    }

    /**
     * @param  array<array-key, mixed>  $raw
     */
    private static function hasText(array $raw): bool
    {
        foreach ($raw as $key => $value) {
            if ($key === 'text' && is_string($value) && trim($value) !== '') {
                return true;
            }

            if (is_array($value) && is_string($value['text'] ?? null) && trim($value['text']) !== '') {
                return true;
            }
        }

        return false;
    }
}

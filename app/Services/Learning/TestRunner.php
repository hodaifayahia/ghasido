<?php

namespace App\Services\Learning;

use App\Enums\TestAttemptStatus;
use App\Models\ActivityPlacement;
use App\Models\Attempt;
use App\Models\MediaAsset;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * The Pre-test / Post-test sitting (TEST-05, TEST-06, TIME-05, DATA-01,
 * DATA-08, DATA-11; spec 0003 Part E, B.6).
 *
 * Owns the clock and the per-question answer rows. The sitting's `started_at`
 * and `deadline_at` are server timestamps written once, so a refresh can
 * never reset the timer; a request that arrives past the deadline is
 * finished server-side, never merely disabled in the UI (TIME-05).
 */
class TestRunner
{
    public function __construct(
        private readonly ActivityPresenter $presenter,
        private readonly TestScorer $scorer,
    ) {}

    /**
     * Resume the learner's in-progress sitting, or open a new one.
     */
    public function startOrResume(User $user, Test $test): TestAttempt
    {
        $open = $test->attempts()
            ->where('user_id', $user->id)
            ->where('status', TestAttemptStatus::InProgress->value)
            ->latest('id')
            ->first();

        if ($open !== null) {
            if ($this->isExpired($open)) {
                $this->scorer->finish($open, TestAttemptStatus::Expired);
            } else {
                return $open;
            }
        }

        $limit = $test->timeLimitSeconds();
        $now = Date::now();

        return $test->attempts()->create([
            'user_id' => $user->id,
            'attempt_no' => (int) $test->attempts()->where('user_id', $user->id)->max('attempt_no') + 1,
            'status' => TestAttemptStatus::InProgress,
            'started_at' => $now,
            'deadline_at' => $limit === null ? null : $now->copy()->addSeconds($limit),
        ]);
    }

    /**
     * The questions of this test, in order (each an activity placement).
     *
     * @return Collection<int, ActivityPlacement>
     */
    public function questions(Test $test): Collection
    {
        // AI drafts stay out of a sitting until the admin releases them (GEN-03).
        return $test->learnerQuestions();
    }

    /**
     * The 1-based question, or a 404 when the number is out of range.
     */
    public function questionAt(Test $test, int $number): ActivityPlacement
    {
        $placement = $this->questions($test)->get($number - 1);

        abort_if($placement === null, 404);

        return $placement;
    }

    /**
     * The saved answer of every question, keyed by 1-based number, so the
     * runner can restore a revisited question and mark the navigator.
     *
     * @return array<int, mixed>
     */
    public function savedAnswers(TestAttempt $attempt, Test $test): array
    {
        $byActivity = $attempt->answers()
            ->get()
            ->keyBy('activity_id');

        $saved = [];

        foreach ($this->questions($test)->values() as $index => $placement) {
            $row = $byActivity->get($placement->activity_id);

            if ($row !== null && $row->raw_answer !== null) {
                $saved[$index + 1] = $row->raw_answer;
            }
        }

        return $saved;
    }

    /**
     * Store (or replace) the answer to one question, verbatim, with its
     * elapsed time and the full answer history (TEST-06, DATA-01, DATA-08).
     *
     * @param  array<string, mixed>  $answer
     */
    public function saveAnswer(TestAttempt $attempt, ActivityPlacement $placement, array $answer, ?int $timeTakenMs): Attempt
    {
        $activity = $placement->activity()->firstOrFail();
        $version = $this->presenter->currentVersion($activity);

        $row = $attempt->answers()
            ->where('activity_id', $activity->id)
            ->first();

        $history = $row !== null && is_array($row->answer_history) ? $row->answer_history : [];
        $history[] = $answer;

        $columns = [
            'user_id' => $attempt->user_id,
            'activity_version_id' => $version->id,
            'attempt_no' => 1,
            'raw_answer' => $answer,
            'answer_history' => $history,
            'response_media_id' => $this->recordingId($answer, $attempt->user_id),
            'time_taken_ms' => $timeTakenMs,
            'submitted_at' => Date::now(),
        ];

        if ($row !== null) {
            $row->update($columns);

            return $row;
        }

        return $attempt->answers()->create([
            ...$columns,
            'activity_id' => $activity->id,
            'started_at' => Date::now(),
        ]);
    }

    public function isExpired(TestAttempt $attempt): bool
    {
        return $attempt->deadline_at !== null
            && $attempt->status === TestAttemptStatus::InProgress
            && Date::now()->greaterThan($attempt->deadline_at);
    }

    /**
     * The first `recording_media_id` found in a speaking answer, verified to
     * be this learner's own upload so an id cannot claim someone else's file
     * (PRIV-04).
     *
     * @param  array<string, mixed>  $answer
     */
    private function recordingId(array $answer, int $userId): ?int
    {
        foreach ($answer as $value) {
            $id = is_array($value) ? ($value['recording_media_id'] ?? null) : null;

            if (! is_numeric($id)) {
                continue;
            }

            $owned = MediaAsset::query()
                ->whereKey((int) $id)
                ->where('uploaded_by', $userId)
                ->exists();

            if ($owned) {
                return (int) $id;
            }
        }

        return null;
    }
}

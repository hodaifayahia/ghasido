<?php

namespace App\Services\Learning;

use App\Enums\ActivityType;
use App\Enums\AnswerShape;
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
     * The learner's last submitted sitting when the test allows only one
     * and no sitting is open: a new one must not start. Null means the
     * learner may start (or resume) as usual. Only a submitted sitting
     * counts; an expired one never unlocked the journey, so it never locks
     * the learner out of it either.
     */
    public function singleAttemptUsed(User $user, Test $test): ?TestAttempt
    {
        if (! $test->isSingleAttempt()) {
            return null;
        }

        $attempts = $test->attempts()->where('user_id', $user->id);

        if ((clone $attempts)->where('status', TestAttemptStatus::InProgress->value)->exists()) {
            return null;
        }

        return $attempts
            ->where('status', TestAttemptStatus::Submitted->value)
            ->latest('id')
            ->first();
    }

    /**
     * The questions of this test, in order (each an activity placement).
     * With `shuffle_questions` on, a sitting gets its own order, seeded from
     * the sitting so it holds across refreshes and moves.
     *
     * @return Collection<int, ActivityPlacement>
     */
    public function questions(Test $test, ?TestAttempt $attempt = null): Collection
    {
        // AI drafts stay out of a sitting until the admin releases them (GEN-03).
        $questions = $test->learnerQuestions();

        if ($attempt === null || ! $test->shufflesQuestions()) {
            return $questions;
        }

        $order = self::seededOrder(
            array_values($questions->map(fn (ActivityPlacement $placement): string => (string) $placement->id)->all()),
            'sitting:'.$attempt->id,
        );
        $byId = $questions->keyBy(fn (ActivityPlacement $placement): string => (string) $placement->id);

        return collect($order)->map(fn (string $id): ActivityPlacement => $byId->get($id) ?? throw new \LogicException('Unknown placement.'))->values();
    }

    /**
     * Put the options of every option-based item in this sitting's own
     * order when the test shuffles options. The order is seeded from the
     * sitting, the placement and the item, so a refresh or a revisit shows
     * the same order; option ids are untouched, so the stored raw answer
     * still names the option the learner chose (TEST-06). Ordering and
     * matching items keep their stored order.
     *
     * @param  array<string, mixed>  $activity  ActivityPresenter::present() output
     * @return array<string, mixed>
     */
    public function presentOptions(array $activity, Test $test, TestAttempt $attempt): array
    {
        $type = is_string($activity['type'] ?? null) ? ActivityType::tryFrom($activity['type']) : null;

        if (! $test->shufflesOptions() || $type?->answerShape() !== AnswerShape::Option || ! is_array($activity['items'] ?? null)) {
            return $activity;
        }

        $items = [];

        foreach ($activity['items'] as $item) {
            if (is_array($item) && is_array($item['options'] ?? null) && array_is_list($item['options'])) {
                $item['options'] = self::shuffleOptions(
                    $item['options'],
                    'sitting:'.$attempt->id.'|question:'.(is_scalar($activity['id'] ?? null) ? $activity['id'] : '').'|item:'.(is_scalar($item['id'] ?? null) ? $item['id'] : ''),
                );
            }

            $items[] = $item;
        }

        $activity['items'] = $items;

        return $activity;
    }

    /**
     * The 1-based question, or a 404 when the number is out of range.
     */
    public function questionAt(Test $test, int $number, ?TestAttempt $attempt = null): ActivityPlacement
    {
        $placement = $this->questions($test, $attempt)->get($number - 1);

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

        foreach ($this->questions($test, $attempt)->values() as $index => $placement) {
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

    /**
     * @param  list<mixed>  $options
     * @return list<mixed>
     */
    private static function shuffleOptions(array $options, string $seed): array
    {
        $keys = array_map(
            static fn (mixed $option, int $index): string => is_array($option) && is_scalar($option['id'] ?? null) ? (string) $option['id'] : (string) $index,
            $options,
            array_keys($options),
        );

        if (count(array_unique($keys)) !== count($keys)) {
            return $options;
        }

        $byKey = array_combine($keys, $options);

        return array_map(static fn (string $key): mixed => $byKey[$key], self::seededOrder($keys, $seed));
    }

    /**
     * A deterministic permutation of the keys for this seed. When the hash
     * order happens to match the stored order it is rotated by one, so a
     * shuffled list never looks unshuffled.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    private static function seededOrder(array $keys, string $seed): array
    {
        if (count(array_unique($keys)) !== count($keys) || count($keys) < 2) {
            return $keys;
        }

        $order = $keys;
        usort($order, static fn (string $a, string $b): int => strcmp(hash('sha256', $seed.'|'.$a), hash('sha256', $seed.'|'.$b)));

        if ($order === $keys) {
            $order[] = array_shift($order);
        }

        return $order;
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

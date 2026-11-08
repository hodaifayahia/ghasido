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
use Illuminate\Support\Facades\DB;

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
                $this->finishExpired($open);
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
     * The question as a sitting shows it: options shuffled by the test's
     * rule, and the ids of ordering lines and matching prompts / targets
     * swapped for opaque tokens of this sitting, the lines and targets in
     * this sitting's own order. Stored ids are written `s1, s2, s3` and pair
     * prompt k with target k, so on a test page they would spell the answer
     * (TEST-03; security audit 2026-11-08). saveAnswer() turns the tokens
     * back into the real ids, so the stored answer and the research export
     * keep naming the real lines (TEST-06, DATA-01).
     *
     * @param  array<string, mixed>  $activity  ActivityPresenter::present() output
     * @return array<string, mixed>
     */
    public function presentForSitting(array $activity, Test $test, TestAttempt $attempt): array
    {
        $activity = $this->presentOptions($activity, $test, $attempt);
        $lists = self::tokenLists(is_string($activity['type'] ?? null) ? ActivityType::tryFrom($activity['type']) : null);

        if ($lists === [] || ! is_array($activity['items'] ?? null)) {
            return $activity;
        }

        $placementId = is_scalar($activity['id'] ?? null) ? (string) $activity['id'] : '';
        $items = [];

        foreach ($activity['items'] as $item) {
            if (is_array($item)) {
                $itemId = is_scalar($item['id'] ?? null) ? (string) $item['id'] : '';

                foreach ($lists as $list => $shuffle) {
                    if (is_array($item[$list] ?? null) && array_is_list($item[$list])) {
                        $item[$list] = self::tokeniseList($item[$list], $attempt, $placementId, $itemId, $list, $shuffle);
                    }
                }
            }

            $items[] = $item;
        }

        $activity['items'] = $items;

        return $activity;
    }

    /**
     * A stored answer (real ids) as the sitting's page knows it (tokens),
     * so a revisited question restores the learner's choice without
     * showing the real ids.
     */
    public function answerForSitting(TestAttempt $attempt, ActivityPlacement $placement, mixed $answer): mixed
    {
        if (! is_array($answer)) {
            return $answer;
        }

        $activity = $placement->activity()->firstOrFail();

        return $this->translateAnswer($attempt, $placement, $activity->type, $this->presenter->currentVersion($activity)->items(), $answer, toReal: false);
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
        // Only an open sitting within its deadline takes an answer; the row
        // is re-read under a lock so a finish racing this write wins
        // (TEST-06, TIME-05; security audit 2026-11-08).
        return DB::transaction(function () use ($attempt, $placement, $answer, $timeTakenMs): Attempt {
            $fresh = TestAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();

            abort_if($fresh === null || ! $fresh->status->acceptsAnswers() || $this->isExpired($fresh), 409);

            return $this->writeAnswer($attempt, $placement, $answer, $timeTakenMs);
        });
    }

    /**
     * Close a sitting whose deadline has passed by the test's timeout rule:
     * `submit` scores what was answered in time, `unanswered` marks it
     * expired (TIME-03; security audit 2026-11-08).
     */
    public function finishExpired(TestAttempt $attempt): void
    {
        $test = $attempt->test()->firstOrFail();

        $this->scorer->finish(
            $attempt,
            $test->onTimeout() === 'unanswered' ? TestAttemptStatus::Expired : TestAttemptStatus::Submitted,
        );
    }

    /**
     * @param  array<string, mixed>  $answer
     */
    private function writeAnswer(TestAttempt $attempt, ActivityPlacement $placement, array $answer, ?int $timeTakenMs): Attempt
    {
        $activity = $placement->activity()->firstOrFail();
        $version = $this->presenter->currentVersion($activity);
        // The page answered with this sitting's tokens; store the real ids
        // (TEST-06). A value that is no token of this sitting (a guessed
        // real id) is stored as null and scores nothing.
        $answer = $this->translateAnswer($attempt, $placement, $activity->type, $version->items(), $answer, toReal: true);

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
     * The lists of an item whose ids are tokenised in a sitting, each with
     * whether its order is shuffled too (a matching item keeps its prompts
     * in place; their ids are what pair them with the targets).
     *
     * @return array<string, bool>
     */
    private static function tokenLists(?ActivityType $type): array
    {
        return match ($type?->answerShape()) {
            AnswerShape::OrderedList => ['sentences' => true, 'cards' => true],
            AnswerShape::PairMap => ['prompts' => false, 'targets' => true],
            default => [],
        };
    }

    /**
     * An opaque id for one entry of one item of one question of this
     * sitting: keyed with the app key, so it cannot be worked out from the
     * page, and different in every sitting.
     */
    private static function token(TestAttempt $attempt, string $placementId, string $itemId, string $list, string $id): string
    {
        $key = config('app.key');

        return 'x'.substr(hash_hmac('sha256', implode('|', [$attempt->id, $placementId, $itemId, $list, $id]), is_string($key) ? $key : ''), 0, 10);
    }

    /**
     * @param  list<mixed>  $entries
     * @return list<mixed>
     */
    private static function tokeniseList(array $entries, TestAttempt $attempt, string $placementId, string $itemId, string $list, bool $shuffle): array
    {
        $out = [];

        foreach ($entries as $entry) {
            if (is_array($entry) && is_scalar($entry['id'] ?? null)) {
                $entry['id'] = self::token($attempt, $placementId, $itemId, $list, (string) $entry['id']);
            }

            $out[] = $entry;
        }

        if (! $shuffle) {
            return $out;
        }

        // No "never unshuffled" rotation here: it would tell the learner the
        // stored order (always the other one for two entries), and the
        // stored order of ordering lines and matching targets encodes the
        // answer (TEST-03; security audit pass 3).
        return self::shuffleOptions($out, 'sitting:'.$attempt->id.'|question:'.$placementId.'|item:'.$itemId.'|'.$list, rotate: false);
    }

    /**
     * Turn this sitting's tokens into the real ids (`$toReal`) or back.
     * Only ordering and matching answers change; an entry that is not one
     * of the item's ids on the other side becomes null (an ordering) or is
     * dropped (a matching prompt), so it can never match the answer.
     *
     * @param  list<array<string, mixed>>  $items  the version's stored items
     * @param  array<array-key, mixed>  $answer
     * @return array<array-key, mixed>
     */
    private function translateAnswer(TestAttempt $attempt, ActivityPlacement $placement, ActivityType $type, array $items, array $answer, bool $toReal): array
    {
        $lists = self::tokenLists($type);

        if ($lists === []) {
            return $answer;
        }

        $placementId = (string) $placement->id;

        foreach ($items as $item) {
            $itemId = is_scalar($item['id'] ?? null) ? (string) $item['id'] : '';
            /** @var array<string, array<string, string>> $maps */
            $maps = [];

            foreach (array_keys($lists) as $list) {
                $maps[$list] = [];

                foreach (is_array($item[$list] ?? null) ? $item[$list] : [] as $entry) {
                    if (is_array($entry) && is_scalar($entry['id'] ?? null)) {
                        $real = (string) $entry['id'];
                        $token = self::token($attempt, $placementId, $itemId, $list, $real);

                        if ($toReal) {
                            $maps[$list][$token] = $real;
                        } else {
                            $maps[$list][$real] = $token;
                        }
                    }
                }
            }

            if (array_key_exists($itemId, $answer)) {
                $answer[$itemId] = self::translateValue($answer[$itemId], $maps);
            } elseif (count($items) === 1 && $answer !== []) {
                // A single item question may post its answer bare
                // (ActivityScorer::answerFor()).
                $bare = self::translateValue($answer, $maps);

                return is_array($bare) ? $bare : $answer;
            }
        }

        return $answer;
    }

    /**
     * @param  array<string, array<string, string>>  $maps
     */
    private static function translateValue(mixed $value, array $maps): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (isset($maps['prompts'], $maps['targets'])) {
            $pairs = [];

            foreach ($value as $prompt => $target) {
                $mapped = $maps['prompts'][(string) $prompt] ?? null;

                if ($mapped !== null) {
                    $pairs[$mapped] = is_scalar($target) ? ($maps['targets'][(string) $target] ?? null) : null;
                }
            }

            return $pairs;
        }

        $lines = array_merge($maps['sentences'] ?? [], $maps['cards'] ?? []);

        return array_map(
            static fn (mixed $id): ?string => is_scalar($id) ? ($lines[(string) $id] ?? null) : null,
            array_values($value),
        );
    }

    /**
     * @param  list<mixed>  $options
     * @return list<mixed>
     */
    private static function shuffleOptions(array $options, string $seed, bool $rotate = true): array
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

        return array_map(static fn (string $key): mixed => $byKey[$key], self::seededOrder($keys, $seed, $rotate));
    }

    /**
     * A deterministic permutation of the keys for this seed. When the hash
     * order happens to match the stored order it is rotated by one, so a
     * shuffled list never looks unshuffled.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    private static function seededOrder(array $keys, string $seed, bool $rotate = true): array
    {
        if (count(array_unique($keys)) !== count($keys) || count($keys) < 2) {
            return $keys;
        }

        // Keyed with the app key: the seed is made of ids the page shows, so
        // a plain hash would let the learner recompute the permutation
        // (security audit pass 3).
        $secret = (string) config('app.key');
        $rank = static fn (string $key): string => hash_hmac('sha256', $seed.'|'.$key, $secret);
        $order = $keys;
        usort($order, static fn (string $a, string $b): int => strcmp($rank($a), $rank($b)));

        if ($rotate && $order === $keys) {
            $order[] = array_shift($order);
        }

        return $order;
    }

    /**
     * Seconds after the deadline during which the client's own auto-submit
     * (fired at 0:00) still lands, so the answer on screen at timeout is not
     * lost (PROG-04, TEST-06). Past it, the sitting is closed (TIME-05).
     */
    public const int DEADLINE_GRACE_SECONDS = 15;

    public function isExpired(TestAttempt $attempt): bool
    {
        return $attempt->deadline_at !== null
            && $attempt->status === TestAttemptStatus::InProgress
            && Date::now()->greaterThan($attempt->deadline_at->copy()->addSeconds(self::DEADLINE_GRACE_SECONDS));
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

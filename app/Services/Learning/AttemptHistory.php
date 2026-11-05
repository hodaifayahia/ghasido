<?php

namespace App\Services\Learning;

use App\Enums\GenerationStatus;
use App\Enums\RoleplayStatus;
use App\Enums\TestAttemptStatus;
use App\Models\ActivityPlacement;
use App\Models\AiScenario;
use App\Models\Attempt;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Models\PronunciationAttempt;
use App\Models\RoleplayAttempt;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Pronunciation\PronunciationPresenter;
use Illuminate\Support\Str;

/**
 * Every earlier attempt a learner can look back on (client report
 * 2026-11-05; PROG-01, TEST-06, DATA-01..05, AIE-04, RP-11).
 *
 * Each try is its own stored row — a new try never overwrites an old one —
 * and this service lists them for the learner's own pages, newest first:
 * the attempt number, when, the score, the feedback and the answer, so they
 * can compare. Always scoped to the signed-in learner's own rows (ROLE-02);
 * a test sitting's score only as far as the admin shows it (TEST-04).
 */
class AttemptHistory
{
    /** Enough rows to compare progress without a heavy page. */
    private const int LIMIT = 30;

    public function __construct(private readonly PronunciationPresenter $pronunciation) {}

    /**
     * The learner's latest practice attempt on this placement, if any.
     */
    public function latestPractice(User $user, ActivityPlacement $placement): ?Attempt
    {
        return Attempt::query()
            ->where('user_id', $user->id)
            ->where('placement_id', $placement->id)
            ->whereNull('test_attempt_id')
            ->orderByDesc('attempt_no')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The learner's practice attempts on one placement, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function practice(User $user, Lesson $lesson, Block $block, ActivityPlacement $placement): array
    {
        $attempts = Attempt::query()
            ->where('user_id', $user->id)
            ->where('placement_id', $placement->id)
            ->whereNull('test_attempt_id')
            ->with('activityVersion')
            ->orderByDesc('attempt_no')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        $checkIds = $attempts
            ->map(fn (Attempt $attempt): mixed => is_array($attempt->ai_feedback) ? ($attempt->ai_feedback['pronunciation_attempt_id'] ?? null) : null)
            ->filter(fn (mixed $id): bool => is_int($id))
            ->values()
            ->all();
        $checks = $checkIds === [] ? collect() : PronunciationAttempt::query()->whereKey($checkIds)->get()->keyBy('id');

        $mediaIds = $attempts->pluck('response_media_id')->filter()->values()->all();
        $media = $mediaIds === [] ? collect() : MediaAsset::query()->whereKey($mediaIds)->get()->keyBy('id');

        $rows = [];

        foreach ($attempts as $attempt) {
            $checkId = is_array($attempt->ai_feedback) ? ($attempt->ai_feedback['pronunciation_attempt_id'] ?? null) : null;
            $check = is_int($checkId) ? $checks->get($checkId) : null;
            $recording = $attempt->response_media_id !== null ? $media->get($attempt->response_media_id) : null;

            $rows[] = [
                'id' => $attempt->id,
                'attemptNo' => $attempt->attempt_no,
                'submittedAt' => ($attempt->submitted_at ?? $attempt->created_at)?->toIso8601String(),
                'score' => $attempt->score === null ? null : (float) $attempt->score,
                'maxScore' => $attempt->max_score === null ? null : (float) $attempt->max_score,
                'isCorrect' => $attempt->is_correct,
                'aiStatus' => $attempt->ai_status?->value,
                'answer' => $this->answerText($attempt),
                'feedback' => $this->practiceFeedback($attempt, $check),
                'recordingUrl' => $recording?->url(),
                'url' => route('learn.lessons.activity', [
                    'lesson' => $lesson,
                    'block' => $block,
                    'placement' => $placement,
                    'attempt' => $attempt->id,
                ]),
            ];
        }

        return $rows;
    }

    /**
     * The learner's sentence checks on a Listen & Repeat step, newest first,
     * each the full result the step renders plus the sentence index and the
     * learner's own recording (spec 0006 §5; DATA-02).
     *
     * @return list<array<string, mixed>>
     */
    public function pronunciation(User $user, Block $block): array
    {
        $attempts = PronunciationAttempt::query()
            ->where('user_id', $user->id)
            ->where('block_id', $block->id)
            ->whereNull('word_index')
            ->whereNull('lexicon_item_id')
            ->with(['lesson', 'recording.media'])
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        $rows = [];

        foreach ($attempts as $attempt) {
            $rows[] = [
                ...$this->pronunciation->present($attempt),
                'item' => $attempt->item_index ?? 0,
                'createdAt' => $attempt->created_at?->toIso8601String(),
                'recordingUrl' => $attempt->recording?->media?->url(),
            ];
        }

        return $rows;
    }

    /**
     * The learner's conversations on one scenario, newest first, each with
     * its score and the way back to its feedback (RP-11, AIE-04). Admin
     * preview runs are not the learner's (RP-13).
     *
     * @return list<array<string, mixed>>
     */
    public function roleplay(User $user, AiScenario $scenario): array
    {
        $attempts = RoleplayAttempt::query()
            ->counted()
            ->where('user_id', $user->id)
            ->where('ai_scenario_id', $scenario->id)
            ->orderByDesc('attempt_no')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        $rows = [];

        foreach ($attempts as $attempt) {
            $feedback = is_array($attempt->feedback) ? $attempt->feedback : [];
            $summary = $feedback['summary_text'] ?? ($feedback['summary_label'] ?? null);

            $rows[] = [
                'id' => $attempt->id,
                'attemptNo' => $attempt->attempt_no,
                'startedAt' => $attempt->started_at->toIso8601String(),
                'status' => $attempt->status->value,
                'aiStatus' => $attempt->ai_status?->value,
                'score' => $attempt->ai_status === GenerationStatus::Done ? $attempt->overall_score : null,
                'turns' => $attempt->employeeTurnsCount(),
                'summary' => is_string($summary) ? Str::limit($summary, 220) : null,
                'url' => $attempt->status === RoleplayStatus::InProgress
                    ? route('learn.roleplay.attempt', ['attempt' => $attempt->id])
                    : route('learn.roleplay.feedback', ['attempt' => $attempt->id]),
            ];
        }

        return $rows;
    }

    /**
     * The learner's finished sittings of one test, newest first. The score
     * travels only when the test shows results at all (TEST-04).
     *
     * @return list<array<string, mixed>>
     */
    public function testSittings(User $user, Test $test): array
    {
        $showScore = $test->resultsVisibility()->showsScore();

        $sittings = TestAttempt::query()
            ->where('user_id', $user->id)
            ->where('test_id', $test->id)
            ->whereIn('status', [TestAttemptStatus::Submitted->value, TestAttemptStatus::Expired->value])
            ->with('test')
            ->orderByDesc('attempt_no')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        $rows = [];

        foreach ($sittings as $sitting) {
            $summary = $sitting->scoreSummary();

            $rows[] = [
                'id' => $sitting->id,
                'attemptNo' => $sitting->attempt_no,
                'submittedAt' => ($sitting->submitted_at ?? $sitting->started_at)->toIso8601String(),
                'percent' => $showScore ? $summary['percent'] : null,
                'passed' => $showScore ? $summary['passed'] : null,
                'url' => route('learn.tests.result', ['test' => $test->id, 'attempt' => $sitting->id]),
            ];
        }

        return $rows;
    }

    /**
     * One line of feedback for a practice row: the pronunciation level, the
     * AI's summary, or where the evaluation stands (PERF-04).
     */
    private function practiceFeedback(Attempt $attempt, ?PronunciationAttempt $check): ?string
    {
        if ($check !== null) {
            $label = PronunciationPresenter::levelLabel($check->level);
            $tip = is_array($check->feedback) && is_string($check->feedback['headline'] ?? null) ? $check->feedback['headline'] : null;

            return $label === null ? $tip : trim($label.($tip !== null ? ' – '.$tip : ''));
        }

        $feedback = $attempt->ai_feedback;

        if ($attempt->ai_status === GenerationStatus::Done && is_array($feedback) && is_string($feedback['summary'] ?? null)) {
            return Str::limit($feedback['summary'], 220);
        }

        return null;
    }

    /**
     * The learner's answer in words: the option they picked, what they
     * typed, the order or the pairs they made (DATA-01). A recording reads
     * as such; the page plays it from `recordingUrl`.
     */
    private function answerText(Attempt $attempt): string
    {
        $items = $attempt->activityVersion?->items() ?? [];
        $byId = [];

        foreach ($items as $item) {
            $byId[(string) ($item['id'] ?? '')] = $item;
        }

        $parts = [];

        foreach ($attempt->raw_answer ?? [] as $itemId => $value) {
            $part = $this->describe($value, $byId[(string) $itemId] ?? []);

            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return Str::limit(implode(' · ', $parts), 280);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function describe(mixed $value, array $item): string
    {
        if (is_string($value) || is_int($value)) {
            return $this->label($item, (string) $value) ?? (string) $value;
        }

        if (! is_array($value)) {
            return '';
        }

        if (array_key_exists('recording_media_id', $value)) {
            return is_string($value['text'] ?? null) && $value['text'] !== ''
                ? $value['text']
                : __('Voice recording');
        }

        if (is_string($value['text'] ?? null)) {
            return $value['text'];
        }

        if (array_is_list($value)) {
            return implode(' → ', array_map(
                fn (mixed $id): string => is_scalar($id) ? ($this->label($item, (string) $id) ?? (string) $id) : '',
                $value,
            ));
        }

        // Blanks: the words typed; pairs: what was matched with what.
        if (is_array($item['blanks'] ?? null)) {
            return implode(', ', array_map(static fn (mixed $word): string => is_scalar($word) ? (string) $word : '', $value));
        }

        $pairs = [];

        foreach ($value as $prompt => $target) {
            $pairs[] = ($this->label($item, (string) $prompt) ?? (string) $prompt)
                .' → '
                .(is_scalar($target) ? ($this->label($item, (string) $target) ?? (string) $target) : '');
        }

        return implode(', ', $pairs);
    }

    /**
     * The words of an option, sentence, card, prompt or target by its id.
     *
     * @param  array<string, mixed>  $item
     */
    private function label(array $item, string $id): ?string
    {
        foreach (['options', 'sentences', 'cards', 'prompts', 'targets', 'left', 'right'] as $list) {
            $entries = $item[$list] ?? null;

            if (! is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (! is_array($entry) || ! is_scalar($entry['id'] ?? null) || (string) $entry['id'] !== $id) {
                    continue;
                }

                foreach (['label', 'text', 'caption', 'audio_text'] as $key) {
                    if (is_string($entry[$key] ?? null) && $entry[$key] !== '') {
                        return $entry[$key];
                    }
                }
            }
        }

        return null;
    }
}

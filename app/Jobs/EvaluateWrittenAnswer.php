<?php

namespace App\Jobs;

use App\Contracts\AiProvider;
use App\Enums\AiFeature;
use App\Enums\GenerationStatus;
use App\Models\ActivityVersion;
use App\Models\Attempt;
use App\Services\Ai\UsageMeter;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Evaluate one written answer against its writing item (WRITE-03, AIE-01,
 * AIE-04, TEST-08, DATA-03; spec 0003 B.9 and Part C).
 *
 * The verbatim answer already sits on the attempt; this job attaches the
 * structured verdict (`ai_feedback`) and the AI score to the same row.
 * A score an admin has overridden is never touched (AIE-05). The version
 * the learner actually answered is read through `activity_version_id`, so
 * a later edit to the activity cannot change what is judged (DATA-11).
 */
class EvaluateWrittenAnswer implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const MAX_SCORE = 100;

    public int $tries = 3;

    /** Seconds; below the worker timeout and DB_QUEUE_RETRY_AFTER. */
    public int $timeout = 600;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly int $attemptId)
    {
        $this->onQueue(Queues::INTERACTIVE);
    }

    public function uniqueId(): string
    {
        return (string) $this->attemptId;
    }

    public function handle(AiProvider $ai, UsageMeter $meter): void
    {
        $attempt = Attempt::query()->find($this->attemptId);

        if ($attempt === null) {
            return;
        }

        $version = $attempt->activityVersion;

        if ($version === null) {
            $this->fail(new RuntimeException(sprintf('Attempt [%d] has no activity version.', $attempt->id)));

            return;
        }

        [$item, $answer] = self::itemAndAnswer($version, $attempt->raw_answer ?? []);

        $attempt->forceFill(['ai_status' => GenerationStatus::Running])->save();

        // Practice at the learner's level, a test answer on the fixed scale;
        // the bar used is stored beside the score (spec 0005 §5.2).
        $level = $attempt->gradingLevel();
        $evaluation = $ai->evaluateWriting($item, $answer, $level);

        $meter->record($attempt->user, AiFeature::WritingEval, $evaluation->usage);

        $changes = [
            'ai_feedback' => $evaluation->toArray(),
            'ai_status' => GenerationStatus::Done,
            'max_score' => $attempt->max_score ?? self::MAX_SCORE,
            'graded_level' => $level,
        ];

        // An admin's override stands through a re-grade; the AI's fresh
        // verdict becomes the recorded original (AIE-05; spec 0005 §2.5).
        if ($attempt->isScoreOverridden()) {
            $changes['original_score'] = $evaluation->overallScore();
        } else {
            $changes['score'] = $evaluation->overallScore();
        }

        $attempt->forceFill($changes)->save();
    }

    public function failed(?Throwable $exception): void
    {
        Attempt::query()
            ->whereKey($this->attemptId)
            ->update(['ai_status' => GenerationStatus::Failed->value]);
    }

    /**
     * Pair the answered item with the text the learner wrote.
     *
     * `raw_answer` is keyed by item id (`{"i1": {"text": "..."}}`, spec
     * B.9); a bare `{"text": "..."}` is tolerated and mapped onto the first
     * item.
     *
     * @param  array<string, mixed>  $rawAnswer
     * @return array{0: array<string, mixed>, 1: string}
     */
    private static function itemAndAnswer(ActivityVersion $version, array $rawAnswer): array
    {
        foreach ($rawAnswer as $itemId => $value) {
            if (! is_array($value)) {
                continue;
            }

            $item = $version->item((string) $itemId);

            if ($item !== null) {
                return [$item, self::text($value)];
            }
        }

        $items = $version->items();
        $first = $items[0] ?? [];

        return [$first, self::text($rawAnswer)];
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    private static function text(array $value): string
    {
        $text = $value['text'] ?? '';

        return is_string($text) ? $text : '';
    }
}

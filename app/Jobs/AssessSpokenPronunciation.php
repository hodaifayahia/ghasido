<?php

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\ActivityVersion;
use App\Models\Attempt;
use App\Models\PronunciationAttempt;
use App\Models\VoiceRecording;
use App\Services\Tts\TtsSettings;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The pronunciation score of a Speaking answer that names what to say
 * (`expected_text`; client report 2026-09-29, spec 0006 §5).
 *
 * The recording already sits on the attempt (TEST-07, DATA-02). This job
 * files it as a pronunciation check against the expected sentence, runs the
 * same check a speaking step runs (CheckPronunciation: listen, align,
 * score, queue the coach), then copies the score onto the attempt row with
 * a link to the check, so the practice page and the reports read one row.
 * An admin's overridden score is never touched (AIE-05). Every path ends in
 * `done` or `failed` (PERF-04).
 */
class AssessSpokenPronunciation implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const MAX_SCORE = 100;

    public int $tries = 3;

    /** Seconds; below the worker timeout and DB_QUEUE_RETRY_AFTER. */
    public int $timeout = 240;

    /** @var list<int> */
    public array $backoff = [5, 15, 45];

    public function __construct(public readonly int $attemptId)
    {
        $this->onQueue(Queues::INTERACTIVE);
    }

    public function uniqueId(): string
    {
        return (string) $this->attemptId;
    }

    /**
     * Does this answered item ask for a pronunciation check?
     *
     * @param  array<string, mixed>  $item
     */
    public static function applies(array $item): bool
    {
        return is_string($item['expected_text'] ?? null) && trim($item['expected_text']) !== '';
    }

    public function handle(): void
    {
        $attempt = Attempt::query()->with(['activityVersion', 'lesson', 'user'])->find($this->attemptId);

        if ($attempt === null || $attempt->ai_status === GenerationStatus::Done) {
            return;
        }

        $version = $attempt->activityVersion;

        if ($version === null || $attempt->response_media_id === null) {
            throw new RuntimeException('The spoken answer has no stored recording.');
        }

        $item = self::itemFor($version, $attempt->raw_answer ?? []);

        if (! self::applies($item)) {
            throw new RuntimeException('The speaking item names no sentence to say.');
        }

        $check = $this->check($attempt, trim((string) $item['expected_text']));

        if (! $check->status->isTerminal()) {
            app()->call([new CheckPronunciation($check->id), 'handle']);
            $check->refresh();
        }

        if ($check->status === GenerationStatus::Failed) {
            throw new RuntimeException($check->failed_reason ?? 'The recording could not be checked.');
        }

        $score = $check->score === null ? 0.0 : (float) $check->score;
        $changes = [
            'ai_status' => GenerationStatus::Done,
            'ai_failed_reason' => null,
            // The check scores 0–100, whatever the item count said.
            'max_score' => self::MAX_SCORE,
            'ai_feedback' => [
                'kind' => 'pronunciation',
                'pronunciation_attempt_id' => $check->id,
                'expected_text' => $check->reference_text,
                'score' => $score,
                'level' => $check->level,
                'scores' => [
                    'words' => $check->words_score === null ? null : (float) $check->words_score,
                    'clarity' => $check->clarity_score === null ? null : (float) $check->clarity_score,
                    'flow' => $check->flow_score === null ? null : (float) $check->flow_score,
                ],
            ],
        ];

        if ($attempt->isScoreOverridden()) {
            $changes['original_score'] = $score;
        } else {
            $changes['score'] = $score;
        }

        $attempt->forceFill($changes)->save();
    }

    public function failed(?Throwable $exception): void
    {
        $reason = Str::limit($exception?->getMessage() ?? 'The spoken answer could not be checked.', 500);

        $attempt = Attempt::query()->find($this->attemptId);

        if ($attempt === null) {
            return;
        }

        $attempt->forceFill(['ai_status' => GenerationStatus::Failed, 'ai_failed_reason' => $reason])->save();

        $checkId = $attempt->ai_feedback['pronunciation_attempt_id'] ?? null;

        if (is_int($checkId)) {
            PronunciationAttempt::query()
                ->whereKey($checkId)
                ->whereNotIn('status', [GenerationStatus::Done->value, GenerationStatus::Failed->value])
                ->update(['status' => GenerationStatus::Failed->value, 'failed_reason' => $reason]);
        }
    }

    /**
     * The pronunciation check filed for this answer, created once (a retry
     * reuses it, so it never pays for the same listen twice).
     */
    private function check(Attempt $attempt, string $expected): PronunciationAttempt
    {
        $existing = $attempt->ai_feedback['pronunciation_attempt_id'] ?? null;

        if (is_int($existing)) {
            $check = PronunciationAttempt::query()->find($existing);

            if ($check !== null) {
                return $check;
            }
        }

        $recording = VoiceRecording::query()
            ->where('media_asset_id', $attempt->response_media_id)
            ->where('user_id', $attempt->user_id)
            ->first();

        if ($recording === null) {
            throw new RuntimeException('The recording row is missing.');
        }

        $user = $attempt->user;
        $lesson = $attempt->lesson;

        $check = PronunciationAttempt::query()->create([
            'user_id' => $attempt->user_id,
            'hotel_id' => $user?->hotel_id,
            'department_id' => $user?->department_id,
            'lesson_id' => $attempt->lesson_id,
            'block_id' => $attempt->block_id,
            'voice_recording_id' => $recording->id,
            'reference_text' => $expected,
            'accent' => $lesson?->speakingAccent() ?? app(TtsSettings::class)->defaultAccent(),
            'attempt_no' => $attempt->attempt_no,
            'status' => GenerationStatus::Pending,
            'duration_ms' => $recording->duration_ms,
        ]);

        $attempt->forceFill([
            'ai_status' => GenerationStatus::Running,
            'ai_feedback' => ['kind' => 'pronunciation', 'pronunciation_attempt_id' => $check->id],
        ])->save();

        return $check;
    }

    /**
     * @param  array<string, mixed>  $rawAnswer
     * @return array<string, mixed>
     */
    private static function itemFor(ActivityVersion $version, array $rawAnswer): array
    {
        foreach (array_keys($rawAnswer) as $itemId) {
            $item = $version->item((string) $itemId);

            if ($item !== null) {
                return $item;
            }
        }

        return $version->items()[0] ?? [];
    }
}

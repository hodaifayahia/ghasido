<?php

namespace App\Jobs;

use App\Contracts\AiProvider;
use App\Contracts\AiUsageInfo;
use App\Contracts\SpeechToTextProvider;
use App\Enums\AiFeature;
use App\Enums\GenerationStatus;
use App\Models\ActivityVersion;
use App\Models\Attempt;
use App\Models\MediaAsset;
use App\Services\Ai\UsageMeter;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Transcribe one recorded spoken answer, then judge it (TEST-07, DATA-02,
 * AIE-01, AIE-04, RESP-05, PRIV-04; spec 0004).
 *
 * The recording never leaves the private disk except as the request body to
 * the configured STT provider. The transcript is stored on the attempt row
 * as soon as it exists, so a retry after an evaluation failure never pays for
 * transcription twice; the structured verdict (criterion → {score, comment})
 * lands in `ai_feedback` on the same row. A score an admin has overridden is
 * never touched (AIE-05). Every path ends in `done` or `failed` (PERF-04).
 */
class TranscribeAndEvaluateSpokenAnswer implements ShouldBeUnique, ShouldQueue
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

    public function handle(SpeechToTextProvider $stt, AiProvider $ai, UsageMeter $meter): void
    {
        $attempt = Attempt::query()->find($this->attemptId);

        if ($attempt === null || $attempt->ai_status === GenerationStatus::Done) {
            return;
        }

        $version = $attempt->activityVersion;

        if ($version === null) {
            throw new RuntimeException(sprintf('Attempt [%d] has no activity version.', $attempt->id));
        }

        $attempt->forceFill(['ai_status' => GenerationStatus::Running, 'ai_failed_reason' => null])->save();

        $transcript = $attempt->transcript;

        if ($transcript === null) {
            $media = $attempt->responseMedia;

            if ($media === null) {
                throw new RuntimeException('The spoken answer has no stored recording.');
            }

            $transcript = $stt->transcribe(self::localPath($media), $media->mime ?? 'audio/webm');
            $attempt->forceFill(['transcript' => $transcript])->save();

            $meter->record($attempt->user, AiFeature::Stt, self::sttUsage());
        }

        $evaluation = $ai->evaluateSpeaking(self::itemFor($version, $attempt->raw_answer ?? []), $transcript);

        $meter->record($attempt->user, AiFeature::SpeakingEval, $evaluation->usage);

        $changes = [
            'ai_feedback' => $evaluation->toArray(),
            'ai_status' => GenerationStatus::Done,
            'ai_failed_reason' => null,
            'max_score' => $attempt->max_score ?? self::MAX_SCORE,
        ];

        if (! $attempt->isScoreOverridden()) {
            $changes['score'] = $evaluation->overallScore();
        }

        $attempt->forceFill($changes)->save();
    }

    public function failed(?Throwable $exception): void
    {
        Attempt::query()
            ->whereKey($this->attemptId)
            ->update([
                'ai_status' => GenerationStatus::Failed->value,
                'ai_failed_reason' => Str::limit($exception?->getMessage() ?? 'The spoken answer could not be evaluated.', 500),
            ]);
    }

    /**
     * A readable local path to the recording. A private local disk has one
     * already; any other driver is copied to a temporary file first.
     */
    private static function localPath(MediaAsset $media): string
    {
        $disk = Storage::disk($media->disk);

        if (! $disk->exists($media->path)) {
            throw new RuntimeException('The recording file is missing from storage.');
        }

        try {
            $path = $disk->path($media->path);

            if (is_readable($path)) {
                return $path;
            }
        } catch (Throwable) {
            // Not a local driver; fall through to a temporary copy.
        }

        $temporary = tempnam(sys_get_temp_dir(), 'rec');

        if ($temporary === false || file_put_contents($temporary, (string) $disk->get($media->path)) === false) {
            throw new RuntimeException('The recording could not be copied for transcription.');
        }

        return $temporary;
    }

    /**
     * The speaking item answered: the first item named in `raw_answer`, or
     * the version's first item.
     *
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

    /**
     * STT is billed per audio minute, not tokens: the row records the call
     * and the model for the cost report (AIL-04).
     */
    private static function sttUsage(): AiUsageInfo
    {
        $provider = config('services.stt.provider');
        $model = config('services.stt.model');

        return new AiUsageInfo(
            promptTokens: 0,
            completionTokens: 0,
            model: is_string($model) && $model !== '' ? $model : 'default',
            provider: is_string($provider) && $provider !== '' ? $provider : 'fake',
        );
    }
}

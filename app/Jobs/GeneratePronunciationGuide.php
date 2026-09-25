<?php

namespace App\Jobs;

use App\Contracts\AiProvider;
use App\Enums\AiFeature;
use App\Enums\GenerationStatus;
use App\Models\PronunciationGuide;
use App\Services\Ai\UsageMeter;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Throwable;

/**
 * Draft the pronunciation guide of one text in one accent with the
 * configured LLM (spec 0006 §4): IPA, syllables, trap words, homophones.
 *
 * Unique per guide and skipped once done, so a retry or two content saves
 * racing never pay twice. A guide an admin edited is never overwritten.
 * Every path ends in `done` or `failed` (PERF-04).
 */
class GeneratePronunciationGuide implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds; below the worker timeout and DB_QUEUE_RETRY_AFTER. */
    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly int $guideId)
    {
        $this->onQueue(Queues::DEFAULT);
    }

    public function uniqueId(): string
    {
        return (string) $this->guideId;
    }

    public function handle(AiProvider $ai, UsageMeter $meter): void
    {
        $guide = PronunciationGuide::query()->find($this->guideId);

        if ($guide === null || $guide->isDone() || $guide->source !== PronunciationGuide::SOURCE_AI) {
            return;
        }

        $guide->forceFill(['status' => GenerationStatus::Running, 'failed_reason' => null])->save();

        $draft = $ai->pronunciationGuide($guide->text, $guide->accent);

        $meter->record(null, AiFeature::PronunciationGuide, $draft->usage, chargePoints: false);

        $guide->forceFill([
            'ipa' => $draft->ipa !== '' ? $draft->ipa : null,
            'words' => $draft->words,
            'tips' => $draft->tips,
            'status' => GenerationStatus::Done,
            'provider' => $draft->usage->provider,
            'model' => $draft->usage->model,
            'generated_at' => Date::now(),
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        PronunciationGuide::query()
            ->whereKey($this->guideId)
            ->where('status', '!=', GenerationStatus::Done->value)
            ->update([
                'status' => GenerationStatus::Failed->value,
                'failed_reason' => Str::limit($exception?->getMessage() ?? 'The pronunciation guide could not be drafted.', 500),
            ]);
    }
}

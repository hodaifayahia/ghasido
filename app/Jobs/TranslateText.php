<?php

namespace App\Jobs;

use App\Contracts\AiProvider;
use App\Enums\AiFeature;
use App\Enums\GenerationStatus;
use App\Models\TextTranslation;
use App\Models\User;
use App\Services\Ai\UsageMeter;
use App\Services\Meaning\HelperLanguages;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Throwable;

/**
 * Translate one English text for Show Meaning (CTRL-01..03; client decision
 * 2026-09-26). The row walks pending → running → done|failed and the tapped
 * button polls it (PERF-04).
 *
 * Unique per text and skipped once done, so many learners tapping the same
 * course title bill one call. Metered for cost, never charged to points.
 */
class TranslateText implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    /** Errors other than a rate limit: three tries, then failed. */
    public int $maxExceptions = 3;

    /** @var list<int> */
    public array $backoff = [5, 15, 30];

    /**
     * A busy provider answers 429; keep the job waiting for up to fifteen
     * minutes instead of failing the learner's tap (PERF-04).
     */
    public function retryUntil(): \DateTimeInterface
    {
        return Date::now()->addMinutes(15);
    }

    public function __construct(public readonly int $translationId)
    {
        $this->onQueue(Queues::INTERACTIVE);
    }

    public function uniqueId(): string
    {
        return (string) $this->translationId;
    }

    public function handle(AiProvider $ai, UsageMeter $meter): void
    {
        $translation = TextTranslation::query()->find($this->translationId);

        // Done, or written by an admin in the meantime: never overwrite it.
        if ($translation === null || $translation->status === GenerationStatus::Done || $translation->source === 'manual') {
            return;
        }

        $translation->forceFill(['status' => GenerationStatus::Running])->save();

        try {
            $draft = $ai->translateText(
                $translation->source_text,
                app(HelperLanguages::class)->name($translation->locale),
            );
        } catch (RequestException $e) {
            // A 429 is either "too busy" (worth waiting for) or an exhausted
            // plan (it will not come back by itself; fail with its reason).
            if ($e->response->status() !== 429) {
                throw $e;
            }

            if (self::quotaExhausted($e)) {
                $this->fail($e);

                return;
            }

            // Rate limited: try again shortly, without using up an attempt.
            $translation->forceFill(['status' => GenerationStatus::Pending])->save();
            $this->release(random_int(20, 60));

            return;
        }

        $meter->record(
            User::query()->find($translation->requested_by),
            AiFeature::Translation,
            $draft->usage,
            chargePoints: false,
        );

        $text = trim($draft->text);

        $translation->forceFill([
            'translation' => $text !== '' ? $text : null,
            'status' => $text !== '' ? GenerationStatus::Done : GenerationStatus::Failed,
            'failed_reason' => $text !== '' ? null : 'empty translation',
        ])->save();
    }

    private static function quotaExhausted(RequestException $e): bool
    {
        $body = strtolower($e->response->body());

        return str_contains($body, 'quota') || str_contains($body, 'insufficient') || str_contains($body, 'arrearage');
    }

    public function failed(?Throwable $exception): void
    {
        TextTranslation::query()
            ->whereKey($this->translationId)
            ->update([
                'status' => GenerationStatus::Failed->value,
                'failed_reason' => Str::limit((string) $exception?->getMessage(), 250),
            ]);
    }
}

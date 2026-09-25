<?php

namespace App\Jobs;

use App\Contracts\AiProvider;
use App\Enums\AiFeature;
use App\Enums\GenerationStatus;
use App\Models\LexiconItem;
use App\Services\Ai\UsageMeter;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Draft the Arabic meaning, explanation and hotel example for one lexicon
 * item (GEN-01, GEN-03, GEN-04; spec 0003 Part C).
 *
 * The result lands in `lexicon_items.ai_draft`, never in the live columns:
 * the admin reviews it and applies it explicitly (GEN-03). `ai_status`
 * walks pending → running → done|failed so the CMS can poll it (PERF-04).
 *
 * Unique per item, so a double click on Generate cannot bill two calls.
 */
class GenerateLexiconDraft implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds; below the worker timeout and DB_QUEUE_RETRY_AFTER. */
    public int $timeout = 600;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly int $lexiconItemId)
    {
        $this->onQueue(Queues::INTERACTIVE);
    }

    public function uniqueId(): string
    {
        return (string) $this->lexiconItemId;
    }

    public function handle(AiProvider $ai, UsageMeter $meter): void
    {
        $item = LexiconItem::query()->find($this->lexiconItemId);

        if ($item === null) {
            return;
        }

        $item->forceFill(['ai_status' => GenerationStatus::Running])->save();

        $department = $item->department;
        $context = $department === null ? '' : sprintf('Hotel department: %s', $department->name);

        $draft = $ai->generateLexicon($item->english_text, $item->kind, $context);

        $meter->record($item->creator, AiFeature::LexiconGenerate, $draft->usage);

        $item->forceFill([
            'ai_draft' => $draft->toArray(),
            'ai_status' => GenerationStatus::Done,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        LexiconItem::query()
            ->whereKey($this->lexiconItemId)
            ->update(['ai_status' => GenerationStatus::Failed->value]);
    }
}

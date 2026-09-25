<?php

namespace App\Jobs;

use App\Contracts\AiProvider;
use App\Enums\AiFeature;
use App\Enums\GenerationStatus;
use App\Models\AiScenario;
use App\Services\Ai\UsageMeter;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Generate one editable scenario draft (GEN-01, GEN-03, GEN-04, PERF-04).
 * The job writes only ai_draft; the published scenario remains untouched
 * until the admin applies the draft explicitly.
 */
final class GenerateScenarioDraft implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds; below the worker timeout and DB_QUEUE_RETRY_AFTER. */
    public int $timeout = 600;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly int $scenarioId)
    {
        $this->onQueue(Queues::INTERACTIVE);
    }

    public function uniqueId(): string
    {
        return (string) $this->scenarioId;
    }

    public function handle(AiProvider $ai, UsageMeter $meter): void
    {
        $scenario = AiScenario::query()->find($this->scenarioId);

        if ($scenario === null) {
            return;
        }

        $scenario->forceFill(['ai_status' => GenerationStatus::Running])->save();

        $draft = $ai->generateScenario(
            $scenario->title,
            $scenario->department->name ?? 'hotel staff',
            $scenario->difficulty,
            $scenario->situation,
        );

        $meter->record($scenario->creator, AiFeature::ScenarioGenerate, $draft->usage);

        $scenario->forceFill([
            'ai_draft' => $draft->toArray(),
            'ai_status' => GenerationStatus::Done,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        AiScenario::query()
            ->whereKey($this->scenarioId)
            ->update(['ai_status' => GenerationStatus::Failed->value]);
    }
}

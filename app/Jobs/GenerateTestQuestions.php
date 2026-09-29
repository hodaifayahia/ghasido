<?php

namespace App\Jobs;

use App\Contracts\AiProvider;
use App\Enums\AiFeature;
use App\Enums\GenerationStatus;
use App\Models\Test;
use App\Models\User;
use App\Services\Ai\UsageMeter;
use App\Services\Tests\TestQuestionGenerator;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Draft a batch of Pre/Post-test questions with the configured AI provider
 * (GEN-01, GEN-03, TEST-02, TSTM-03, PERF-04, AIL-04; spec 0004).
 *
 * Reads what was asked from `tests.ai_request`, calls the provider once,
 * meters the call and stores the questions as unreleased drafts through
 * TestQuestionGenerator. Unique per test, and a run that already finished is
 * a no-op, so a retry never bills twice or stores a second batch.
 */
class GenerateTestQuestions implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** Seconds; below the worker timeout and DB_QUEUE_RETRY_AFTER. */
    public int $timeout = 600;

    /** @var list<int> */
    public array $backoff = [30];

    public function __construct(public readonly int $testId)
    {
        $this->onQueue(Queues::DEFAULT);
    }

    public function uniqueId(): string
    {
        return (string) $this->testId;
    }

    public function handle(AiProvider $ai, UsageMeter $meter, TestQuestionGenerator $generator): void
    {
        $test = Test::query()->with('department')->find($this->testId);

        if ($test === null || $test->ai_status === GenerationStatus::Done || $test->ai_status === GenerationStatus::Failed) {
            return;
        }

        $request = $test->ai_request ?? [];
        $skills = array_values(array_filter(
            is_array($request['skills'] ?? null) ? $request['skills'] : [],
            'is_string',
        ));

        if ($skills === []) {
            throw new RuntimeException('No question skills were requested.');
        }

        $avoid = array_values(array_filter(is_array($request['avoid'] ?? null) ? $request['avoid'] : [], 'is_string'));
        $actor = User::query()->find((int) ($request['actor_id'] ?? 0));

        if ($actor === null) {
            throw new RuntimeException('The admin who asked for these questions no longer exists.');
        }

        $test->forceFill(['ai_status' => GenerationStatus::Running])->save();

        $draft = $ai->generateTestQuestions(
            $test->department->name ?? __('all hotel departments'),
            is_string($request['level'] ?? null) ? $request['level'] : 'A2',
            $skills,
            is_string($request['prompt'] ?? null) ? $request['prompt'] : '',
            $avoid,
        );

        $meter->record($actor, AiFeature::TestQuestionsGenerate, $draft->usage);

        if ($draft->questions === []) {
            throw new RuntimeException('The AI returned no usable questions. Try again or change the prompt.');
        }

        $ids = $generator->store($test, $draft, $actor);

        $test->forceFill([
            'ai_status' => GenerationStatus::Done,
            'ai_failed_reason' => null,
            'ai_request' => [...$request, 'placement_ids' => $ids],
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        Test::query()
            ->whereKey($this->testId)
            ->update([
                'ai_status' => GenerationStatus::Failed->value,
                'ai_failed_reason' => Str::limit($exception?->getMessage() ?? 'Question generation failed.', 500),
            ]);
    }
}

<?php

namespace App\Jobs;

use App\Contracts\AiProvider;
use App\Enums\AiFeature;
use App\Enums\ContentGenerationType;
use App\Enums\GenerationStatus;
use App\Models\ContentGeneration;
use App\Services\Ai\UsageMeter;
use App\Services\Content\LessonGenerator;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Write one AI lesson, or plan a course and fan out one job per lesson
 * (GEN-01, GEN-03, PERF-04; spec 0004).
 *
 * `$lessonIndex` null = the course outline step: ask for the outline once,
 * store it, then dispatch one job per outline row. Otherwise write the
 * lesson for that index. Unique per (generation, index), and an index that
 * already has a lesson is skipped before any provider call, so a retry never
 * bills twice or writes a second lesson.
 */
class GenerateLessonFromPrompt implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds; below the worker timeout and DB_QUEUE_RETRY_AFTER. */
    public int $timeout = 600;

    /** @var list<int> */
    public array $backoff = [15, 60, 120];

    public function __construct(
        public readonly int $generationId,
        public readonly ?int $lessonIndex = null,
    ) {
        $this->onQueue(Queues::DEFAULT);
    }

    public function uniqueId(): string
    {
        return $this->generationId.':'.($this->lessonIndex ?? 'outline');
    }

    public function handle(AiProvider $ai, UsageMeter $meter, LessonGenerator $generator): void
    {
        $generation = ContentGeneration::query()->find($this->generationId);

        if ($generation === null || $generation->status === GenerationStatus::Failed) {
            return;
        }

        if ($this->lessonIndex === null) {
            $this->outline($generation, $ai, $meter, $generator);

            return;
        }

        if ($generation->lessonIdFor($this->lessonIndex) !== null) {
            $generator->refreshStatus($generation);

            return;
        }

        $generation->markRunning(ContentGeneration::STAGE_WRITING);

        $topic = $generation->outlineLesson($this->lessonIndex);
        $notes = $generation->type === ContentGenerationType::Course
            ? sprintf(
                'Lesson %d of %d in the course "%s". Lesson title: %s. Admin request: %s',
                $this->lessonIndex + 1,
                $generation->lessons_total,
                (string) (($generation->outline ?? [])['title'] ?? ''),
                $topic['title'],
                $generation->prompt,
            )
            : '';

        $draft = $ai->generateLesson(
            $topic['topic'],
            $generator->departmentName($generation),
            (string) $generation->level,
            $notes,
        );

        $meter->record($generation->user, AiFeature::LessonGenerate, $draft->usage);

        $generator->buildLesson($generation->refresh(), $this->lessonIndex, $draft);
        $generator->refreshStatus($generation);
    }

    private function outline(ContentGeneration $generation, AiProvider $ai, UsageMeter $meter, LessonGenerator $generator): void
    {
        if (($generation->outline['lessons'] ?? []) === []) {
            $generation->markRunning(ContentGeneration::STAGE_OUTLINE);

            $outline = $ai->generateCourseOutline(
                $generation->prompt,
                $generator->departmentName($generation),
                (string) $generation->level,
                $generation->requestedLessonCount(),
            );

            $meter->record($generation->user, AiFeature::CourseOutline, $outline->usage);
            $generator->writeOutline($generation, $outline);
            $generation->refresh();
        }

        $generation->markRunning(ContentGeneration::STAGE_WRITING);

        for ($index = 0; $index < $generation->lessons_total; $index++) {
            if ($generation->lessonIdFor($index) === null) {
                self::dispatch($generation->id, $index);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $generation = ContentGeneration::query()->find($this->generationId);

        if ($generation === null) {
            return;
        }

        $step = $this->lessonIndex === null
            ? __('Planning the course failed')
            : __('Writing lesson :number failed', ['number' => $this->lessonIndex + 1]);

        $generation->markFailed(sprintf('%s: %s', $step, $exception?->getMessage() ?? __('unknown error')));
    }
}

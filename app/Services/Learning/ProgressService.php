<?php

namespace App\Services\Learning;

use App\Models\Block;
use App\Models\BlockCompletion;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Writes learner progress, one step at a time (PROG-01, PROG-03, PROG-04,
 * DATA-06, DATA-07; spec 0003 Part E).
 *
 * Every completion is a row written the moment it happens, idempotently, so
 * a retried request leaves exactly one row and a device switch loses nothing
 * (AUTH-09). The lesson completion is derived from the block rows at write
 * time, never trusted from the client.
 */
class ProgressService
{
    public function __construct(private readonly JourneyService $journey) {}

    /**
     * Mark one step done and roll it up.
     *
     * Returns whether this call completed the lesson (as opposed to it
     * already having been complete, or still having steps left).
     */
    public function completeBlock(User $user, Lesson $lesson, Block $block): bool
    {
        return DB::transaction(function () use ($user, $lesson, $block): bool {
            BlockCompletion::query()->firstOrCreate(
                ['user_id' => $user->id, 'block_id' => $block->id],
                ['lesson_id' => $lesson->id, 'completed_at' => now()],
            );

            $lessonJustCompleted = false;

            if ($this->allVisibleBlocksDone($user, $lesson)) {
                $completion = LessonCompletion::query()->firstOrCreate(
                    ['user_id' => $user->id, 'lesson_id' => $lesson->id],
                    ['completed_at' => now()],
                );

                $lessonJustCompleted = $completion->wasRecentlyCreated;
            }

            $this->touch($user, $lessonJustCompleted);
            $this->journey->forget($user);

            return $lessonJustCompleted;
        });
    }

    /**
     * Note that the learner did something (DATA-06), and stamp the start and
     * end of their training the first time each becomes true (DATA-07).
     */
    public function touch(User $user, bool $lessonJustCompleted = false): void
    {
        $changes = ['last_activity_at' => now()];

        if ($user->training_started_at === null) {
            $changes['training_started_at'] = now();
        }

        if ($lessonJustCompleted
            && $user->training_completed_at === null
            && $this->journey->lessonsTotal($user) > 0
            && $this->journey->lessonsCompleted($user) >= $this->journey->lessonsTotal($user)) {
            $changes['training_completed_at'] = now();
        }

        $user->forceFill($changes)->saveQuietly();
    }

    /**
     * Per-course progress for the My Progress page (PROG-02).
     *
     * @return list<array{id: int, title: string, tone: string, description: string|null, lessonsTotal: int, lessonsCompleted: int, percent: int}>
     */
    public function courseProgress(User $user): array
    {
        /** @var list<int> $completedIds */
        $completedIds = $user->lessonCompletions()->pluck('lesson_id')->map(fn ($id): int => (int) $id)->all();

        $rows = [];

        $courses = Course::query()
            ->forLearner($user)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        foreach ($courses as $course) {
            /** @var list<int> $lessonIds */
            $lessonIds = $course->orderedLessons()->pluck('lessons.id')->map(fn ($id): int => (int) $id)->all();
            $total = count($lessonIds);
            $completed = count(array_intersect($lessonIds, $completedIds));

            $rows[] = [
                'id' => $course->id,
                'title' => $course->title,
                'tone' => $course->tone,
                'description' => $course->description,
                'lessonsTotal' => $total,
                'lessonsCompleted' => $completed,
                'percent' => $total === 0 ? 0 : (int) round($completed / $total * 100),
            ];
        }

        return $rows;
    }

    private function allVisibleBlocksDone(User $user, Lesson $lesson): bool
    {
        $visible = $lesson->visibleBlocks()->pluck('id')->map(fn ($id): int => (int) $id);

        if ($visible->isEmpty()) {
            return false;
        }

        $done = $user->blockCompletions()
            ->where('lesson_id', $lesson->id)
            ->pluck('block_id')
            ->map(fn ($id): int => (int) $id);

        return $visible->diff($done)->isEmpty();
    }
}

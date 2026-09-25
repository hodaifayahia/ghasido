<?php

namespace App\Services\Learning;

use App\Models\Block;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Where a learner is in their curriculum and where they go next (JOURNEY-02,
 * LESSON-03, LESSON-04, PROG-05; spec 0003 Part E).
 *
 * The order is one rule read everywhere: courses by position, then each
 * course's lessons by unit position then lesson position (Course::
 * orderedLessons()), then a lesson's visible blocks by position. "Continue
 * where you left off" is the first uncompleted visible block of the first
 * uncompleted lesson in that order, computed on the server from the
 * completion rows, never on the client (PROG-01).
 */
class LessonNavigator
{
    /**
     * Every lesson this learner may open, in learner order.
     *
     * @return Collection<int, Lesson>
     */
    public function orderedLessons(User $user): Collection
    {
        $courses = Course::query()
            ->forLearner($user)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        /** @var Collection<int, Lesson> $lessons */
        $lessons = new Collection;

        foreach ($courses as $course) {
            $lessons = $lessons->concat($course->orderedLessons()->get());
        }

        return $lessons;
    }

    /**
     * @return list<int>
     */
    public function completedLessonIds(User $user): array
    {
        /** @var list<int> $ids */
        $ids = $user->lessonCompletions()->pluck('lesson_id')->map(fn ($id): int => (int) $id)->all();

        return $ids;
    }

    /**
     * @return list<int>
     */
    public function completedBlockIds(User $user, Lesson $lesson): array
    {
        /** @var list<int> $ids */
        $ids = $user->blockCompletions()
            ->where('lesson_id', $lesson->id)
            ->pluck('block_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $ids;
    }

    /**
     * The first lesson in learner order without a completion row, or null
     * when every lesson is done (or there are none).
     */
    public function firstUncompletedLesson(User $user): ?Lesson
    {
        $completed = $this->completedLessonIds($user);

        foreach ($this->orderedLessons($user) as $lesson) {
            if (! in_array($lesson->id, $completed, true)) {
                return $lesson;
            }
        }

        return null;
    }

    /**
     * The first visible block of this lesson the learner has not completed,
     * or null when they all are.
     */
    public function firstUncompletedBlock(User $user, Lesson $lesson): ?Block
    {
        $completed = $this->completedBlockIds($user, $lesson);

        foreach ($lesson->visibleBlocks()->get() as $block) {
            if (! in_array($block->id, $completed, true)) {
                return $block;
            }
        }

        return null;
    }

    /**
     * Where opening a lesson lands: the first uncompleted step, or the first
     * step when the lesson is already complete (LESSON-04 lets a learner
     * revisit). Null only for a lesson with no visible blocks.
     */
    public function entryBlock(User $user, Lesson $lesson): ?Block
    {
        return $this->firstUncompletedBlock($user, $lesson)
            ?? $lesson->visibleBlocks()->first();
    }

    /**
     * The lesson and block "Continue where you left off" points at, or null
     * before the Pre-test (lessons are locked, JOURNEY-01) and once every
     * lesson is complete.
     *
     * @return array{lesson: Lesson, block: Block}|null
     */
    public function continueTarget(User $user): ?array
    {
        if (! $user->lessonsUnlocked()) {
            return null;
        }

        $lesson = $this->firstUncompletedLesson($user);

        if ($lesson === null) {
            return null;
        }

        $block = $this->entryBlock($user, $lesson);

        if ($block === null) {
            return null;
        }

        return ['lesson' => $lesson, 'block' => $block];
    }

    public function continueUrl(User $user): ?string
    {
        $target = $this->continueTarget($user);

        return $target === null ? null : $this->stepUrl($target['lesson'], $target['block']);
    }

    public function stepUrl(Lesson $lesson, Block $block): string
    {
        return route('learn.lessons.step', ['lesson' => $lesson, 'block' => $block]);
    }

    /**
     * The step tracker for one lesson (LESSON-03: "Step 3 of 7").
     *
     * @return list<array{number: int, label: string, type: string, url: string, done: bool, current: bool}>
     */
    public function steps(User $user, Lesson $lesson, Block $current): array
    {
        $completed = $this->completedBlockIds($user, $lesson);
        $steps = [];

        foreach ($lesson->visibleBlocks()->get()->values() as $index => $block) {
            $steps[] = [
                'number' => $index + 1,
                'label' => $block->type->stepLabel(),
                'type' => $block->type->value,
                'url' => $this->stepUrl($lesson, $block),
                'done' => in_array($block->id, $completed, true),
                'current' => $block->id === $current->id,
            ];
        }

        return $steps;
    }

    /**
     * The visible block before and after this one.
     *
     * @return array{prev: Block|null, next: Block|null}
     */
    public function neighbours(Lesson $lesson, Block $current): array
    {
        /** @var list<Block> $blocks */
        $blocks = $lesson->visibleBlocks()->get()->values()->all();

        foreach ($blocks as $index => $block) {
            if ($block->id === $current->id) {
                return [
                    'prev' => $blocks[$index - 1] ?? null,
                    'next' => $blocks[$index + 1] ?? null,
                ];
            }
        }

        return ['prev' => null, 'next' => null];
    }

    /**
     * The lesson after this one in learner order, across courses.
     */
    public function nextLessonAfter(User $user, Lesson $lesson): ?Lesson
    {
        /** @var list<Lesson> $lessons */
        $lessons = $this->orderedLessons($user)->values()->all();

        foreach ($lessons as $index => $candidate) {
            if ($candidate->id === $lesson->id) {
                return $lessons[$index + 1] ?? null;
            }
        }

        return null;
    }
}

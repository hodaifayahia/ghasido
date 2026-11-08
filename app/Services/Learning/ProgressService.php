<?php

namespace App\Services\Learning;

use App\Enums\EnglishLevel;
use App\Models\ActivityPlacement;
use App\Models\Attempt;
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

            if ($this->allVisibleBlocksDone($user, $lesson) && $this->practicePassed($user, $lesson)) {
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

    /**
     * The learner's path on Home (client request 2026-09-30): their level on
     * the Beginner → Intermediate → Advanced track, and the modules (the
     * courses of their department and level) in order with how many
     * lessons of each are done, ending with the Post-test.
     *
     * @return array<string, mixed>
     */
    public function learningPath(User $user): array
    {
        $modules = $this->courseProgress($user);
        $current = $user->english_level;
        $order = EnglishLevel::cases();
        $currentIndex = $current === null ? -1 : array_search($current, $order, true);

        $foundCurrent = false;
        $steps = [];

        foreach ($modules as $module) {
            $done = $module['lessonsTotal'] > 0 && $module['lessonsCompleted'] >= $module['lessonsTotal'];
            $state = $done ? 'done' : ($foundCurrent ? 'next' : 'current');
            $foundCurrent = $foundCurrent || ! $done;

            $steps[] = [
                'id' => $module['id'],
                'title' => $module['title'],
                'lessonsCompleted' => $module['lessonsCompleted'],
                'lessonsTotal' => $module['lessonsTotal'],
                'percent' => $module['percent'],
                'state' => $state,
            ];
        }

        $completed = count(array_filter($steps, fn (array $step): bool => $step['state'] === 'done'));

        return [
            'level' => $current?->value,
            'levelLabel' => $current?->label(),
            'department' => $user->department->name ?? null,
            'levels' => array_map(fn (EnglishLevel $level, int $index): array => [
                'value' => $level->value,
                'label' => $level->label(),
                'state' => $currentIndex === false || $currentIndex < 0
                    ? 'next'
                    : ($index < $currentIndex ? 'done' : ($index === $currentIndex ? 'current' : 'next')),
            ], $order, array_keys($order)),
            'modules' => $steps,
            'modulesCompleted' => $completed,
            'modulesTotal' => count($steps),
            'postTest' => [
                'unlocked' => $this->journey->postTestUnlocked($user),
                'submitted' => $this->journey->postTestSubmitted($user),
            ],
        ];
    }

    /**
     * The lesson's `practice_passed` rule (JOURNEY-04): every published,
     * auto-scored activity placed on a visible step of the lesson needs a
     * practice attempt whose score reaches `min_score` percent (the best
     * attempt counts). Spoken and written activities are judged by the AI
     * later and carry no automatic score, so they do not hold the lesson
     * back. A missing `min_score` asks for one scored attempt of each.
     * Every other rule is satisfied by the steps alone. A pass recorded
     * after the last step counts on the learner's next step completion
     * (security audit 2026-11-08).
     */
    private function practicePassed(User $user, Lesson $lesson): bool
    {
        $condition = is_array($lesson->completion_condition) ? $lesson->completion_condition : [];

        if (($condition['rule'] ?? null) !== 'practice_passed') {
            return true;
        }

        $minimum = is_numeric($condition['min_score'] ?? null) ? (float) $condition['min_score'] : 0.0;

        $placements = ActivityPlacement::query()
            ->where('placeable_type', (new Block)->getMorphClass())
            ->whereIn('placeable_id', $lesson->visibleBlocks()->select('id'))
            ->with('activity')
            ->get()
            ->filter(fn (ActivityPlacement $placement): bool => $placement->activity !== null
                && $placement->activity->isPublished()
                && $placement->activity->isAutoScored());

        foreach ($placements as $placement) {
            $best = Attempt::query()
                ->where('user_id', $user->id)
                ->where('placement_id', $placement->id)
                ->whereNull('test_attempt_id')
                ->whereNotNull('score')
                ->where('max_score', '>', 0)
                ->get(['score', 'max_score'])
                ->map(fn (Attempt $attempt): float => (float) $attempt->score / (float) $attempt->max_score * 100)
                ->max();

            if ($best !== null && $best + 0.0001 >= $minimum) {
                continue;
            }

            // A learner who has used every allowed attempt cannot pass any
            // more: the activity then counts as done, so a limited activity
            // never locks the lesson, the Post-test and the certificate for
            // good (JOURNEY-04; security audit pass 3).
            $allowed = (int) $placement->activity->attempts_allowed;
            $used = Attempt::query()
                ->where('user_id', $user->id)
                ->where('placement_id', $placement->id)
                ->whereNull('test_attempt_id')
                ->count();

            if ($allowed > 0 && $used >= $allowed) {
                continue;
            }

            return false;
        }

        return true;
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

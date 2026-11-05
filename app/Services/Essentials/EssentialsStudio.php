<?php

namespace App\Services\Essentials;

use App\Enums\ContentStatus;
use App\Enums\EssentialsIcon;
use App\Enums\EssentialsLessonTemplate;
use App\Models\Block;
use App\Models\BlockCompletion;
use App\Models\Course;
use App\Models\Department;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * What the English Essentials studio shows (client request 2026-11-05):
 * every topic as a card with its audience, visibility and use, and the
 * open topic's lessons as one flat, ordered list. Read-only: every write
 * goes through LessonService with its audit row.
 *
 * A hotel admin sees the shared topics and their own hotel's, like the
 * lesson builder (CMS-04, ORG-04); writes are authorized by CoursePolicy
 * and LessonPolicy in the controllers (SEC-01).
 */
class EssentialsStudio
{
    /**
     * @return array<string, mixed>
     */
    public function page(User $user, int $requestedTopic): array
    {
        $topics = $this->topics($user);
        $open = $topics->firstWhere('id', $requestedTopic) ?? $topics->first();

        $departments = Department::query()->active()->globalCatalogue()->orderBy('position')->orderBy('name')->get(['id', 'name']);
        $departmentNames = $departments->pluck('name', 'id');

        /** @var list<int> $lessonIds */
        $lessonIds = Lesson::query()->whereIn('course_id', $topics->pluck('id'))->notArchived()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $learners = $this->learnersByCourse($lessonIds);

        return [
            'stats' => [
                'topics' => $topics->count(),
                'visibleTopics' => $topics->filter(fn (Course $topic): bool => $topic->isPublished())->count(),
                'lessons' => count($lessonIds),
                'publishedLessons' => Lesson::query()->whereIn('id', $lessonIds)->published()->count(),
                'learners' => $lessonIds === [] ? 0 : BlockCompletion::query()->whereIn('lesson_id', $lessonIds)->distinct()->count('user_id'),
                'helpLinks' => $lessonIds === [] ? 0 : Block::query()->whereIn('help_lesson_id', $lessonIds)->count(),
            ],
            'topics' => array_values($topics->map(fn (Course $topic): array => $this->topicRow($topic, $departmentNames->all(), $learners))->all()),
            'openTopic' => $open === null ? null : [
                'id' => $open->id,
                'lessons' => $this->lessons($open),
            ],
            'departments' => array_values($departments->map(fn (Department $department): array => ['value' => $department->id, 'label' => $department->name])->all()),
            'icons' => EssentialsIcon::options(),
            'templates' => EssentialsLessonTemplate::options(),
        ];
    }

    /**
     * @return Collection<int, Course>
     */
    private function topics(User $user): Collection
    {
        return Course::query()
            ->essentials()
            ->notArchived()
            ->sharedOrFor($user->hotel_id)
            ->with('audienceDepartments:id')
            ->withCount([
                'lessons as lessons_total' => fn (Builder $lessons) => $lessons->whereNull('lessons.archived_at'),
                'lessons as lessons_published' => fn (Builder $lessons) => $lessons->whereNull('lessons.archived_at')->where('lessons.status', ContentStatus::Published),
            ])
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<int|string, mixed>  $departmentNames
     * @param  array<int, int>  $learners
     * @return array<string, mixed>
     */
    private function topicRow(Course $topic, array $departmentNames, array $learners): array
    {
        /** @var list<int> $audience */
        $audience = $topic->audienceDepartments->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();

        return [
            'id' => $topic->id,
            'title' => $topic->title,
            'description' => $topic->description,
            'icon' => ($topic->icon ?? EssentialsIcon::Sprout)->value,
            'tone' => $topic->tone,
            'visible' => $topic->isPublished(),
            'audience' => $audience,
            'audienceLabels' => array_values(array_filter(array_map(fn (int $id): ?string => isset($departmentNames[$id]) ? (string) $departmentNames[$id] : null, $audience))),
            'lessonsTotal' => (int) ($topic->getAttribute('lessons_total') ?? 0),
            'lessonsPublished' => (int) ($topic->getAttribute('lessons_published') ?? 0),
            'learners' => $learners[$topic->id] ?? 0,
            'shared' => $topic->hotel_id === null,
            'updateUrl' => route('courses.update', ['course' => $topic]),
            'destroyUrl' => route('courses.destroy', ['course' => $topic]),
            'addLessonUrl' => route('essentials-studio.lessons.store', ['course' => $topic]),
            'reorderUrl' => route('essentials-studio.lessons.reorder', ['course' => $topic]),
        ];
    }

    /**
     * The topic's lessons in learner order, with what each one holds.
     *
     * @return list<array<string, mixed>>
     */
    private function lessons(Course $topic): array
    {
        $lessons = $topic->orderedLessons(false)
            ->whereNull('lessons.archived_at')
            ->whereNull('units.archived_at')
            ->with(['blocks' => fn ($blocks) => $blocks->orderBy('position')])
            ->get();

        /** @var list<int> $ids */
        $ids = $lessons->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $helpCounts = $ids === [] ? [] : Block::query()->whereIn('help_lesson_id', $ids)
            ->selectRaw('help_lesson_id, count(*) as total')->groupBy('help_lesson_id')->pluck('total', 'help_lesson_id')->all();

        $learnerCounts = $ids === [] ? [] : BlockCompletion::query()->whereIn('lesson_id', $ids)
            ->selectRaw('lesson_id, count(distinct user_id) as total')->groupBy('lesson_id')->pluck('total', 'lesson_id')->all();

        return array_values($lessons->map(fn (Lesson $lesson): array => [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'published' => $lesson->status === ContentStatus::Published,
            'estimatedMinutes' => $lesson->estimated_minutes,
            'steps' => array_values($lesson->blocks
                ->filter(fn (Block $block): bool => $block->is_visible)
                ->map(fn (Block $block): array => ['type' => $block->type->value, 'label' => $block->type->stepLabel()])
                ->values()
                ->all()),
            'helpLinks' => (int) ($helpCounts[$lesson->id] ?? 0),
            'learners' => (int) ($learnerCounts[$lesson->id] ?? 0),
            'editUrl' => route('lessons.edit', ['lesson' => $lesson]),
            'previewUrl' => route('lessons.preview', ['lesson' => $lesson]),
            'publishUrl' => route('lessons.publish', ['lesson' => $lesson]),
            'destroyUrl' => route('lessons.destroy', ['lesson' => $lesson]),
        ])->all());
    }

    /**
     * Distinct learners who completed any step, per topic.
     *
     * @param  list<int>  $lessonIds
     * @return array<int, int>
     */
    private function learnersByCourse(array $lessonIds): array
    {
        if ($lessonIds === []) {
            return [];
        }

        return BlockCompletion::query()
            ->join('lessons', 'lessons.id', '=', 'block_completions.lesson_id')
            ->whereIn('block_completions.lesson_id', $lessonIds)
            ->selectRaw('lessons.course_id as course_id, count(distinct block_completions.user_id) as total')
            ->groupBy('lessons.course_id')
            ->pluck('total', 'course_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }
}

<?php

namespace App\Services\Learning;

use App\Enums\EssentialsIcon;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The English Essentials topics a learner sees (client request 2026-10-18):
 * beginner basics (letters and spelling, numbers, time, greetings, asking
 * for clarification) that sit beside the department training on My Lessons.
 *
 * Progress is the ordinary block and lesson completion rows (PROG-01), read
 * here per topic. Nothing in the journey, the Post-test gate or the
 * certificate reads these topics: they use Course::forLearner, which never
 * returns an Essentials course.
 */
class EssentialsCatalogue
{
    /**
     * @return Collection<int, Course>
     */
    public function topics(User $user): Collection
    {
        return Course::query()
            ->essentialsFor($user)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * One row per topic for the Essentials page.
     *
     * @param  list<int>  $completed
     * @return list<array<string, mixed>>
     */
    public function rows(User $user, array $completed): array
    {
        // A topic with no published lesson yet is not shown: an empty title
        // would only lead nowhere (client request 2026-11-05).
        return array_values($this->topics($user)->map(function (Course $course) use ($completed): ?array {
            /** @var list<int> $ids */
            $ids = $course->orderedLessons()->pluck('lessons.id')->map(fn ($id): int => (int) $id)->all();

            if ($ids === []) {
                return null;
            }

            return [
                'id' => $course->id,
                'title' => $course->title,
                'description' => $course->description,
                'icon' => ($course->icon ?? EssentialsIcon::Sprout)->value,
                'tone' => $course->tone,
                'lessonsTotal' => count($ids),
                'lessonsCompleted' => count(array_intersect($ids, $completed)),
                'url' => route('learn.essentials.show', ['course' => $course]),
            ];
        })->filter()->values()->all());
    }

    /**
     * The English Essentials card on My Lessons, or null when nothing is
     * published for this learner yet (the card is then not shown).
     *
     * @param  list<int>  $completed
     * @return array{topics: int, lessonsTotal: int, lessonsCompleted: int, url: string, items: list<array<string, mixed>>}|null
     */
    public function summary(User $user, array $completed): ?array
    {
        $rows = $this->rows($user, $completed);

        if ($rows === []) {
            return null;
        }

        return [
            'topics' => count($rows),
            'lessonsTotal' => array_sum(array_map(fn (array $row): int => (int) $row['lessonsTotal'], $rows)),
            'lessonsCompleted' => array_sum(array_map(fn (array $row): int => (int) $row['lessonsCompleted'], $rows)),
            'url' => route('learn.essentials.index'),
            // Listed down the side of My Lessons (client request 2026-11-05).
            'items' => $rows,
        ];
    }
}

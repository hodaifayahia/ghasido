<?php

namespace App\Services\Learning;

use App\Models\Block;
use App\Models\Lesson;
use App\Models\User;

/**
 * A learner's side trip from a department lesson step into an English
 * Essentials lesson, and the way back (client request 2026-10-18).
 *
 * The step a learner leaves from is kept in their session as ids only,
 * never as a URL, so the way back can only ever lead to a lesson step the
 * learner may still open (no open redirect, ROLE-02). It is read for the
 * Essentials lesson the trip was made to and forgotten on the way back or
 * when that lesson is opened from My Lessons.
 */
class EssentialsDetour
{
    private const string KEY = 'essentials.detour';

    public function __construct(private readonly LessonNavigator $navigator) {}

    public function start(Lesson $from, Block $fromBlock, Lesson $essentials): void
    {
        session()->put(self::KEY, [
            'lesson' => $from->id,
            'block' => $fromBlock->id,
            'essentials' => $essentials->id,
        ]);
    }

    public function forget(): void
    {
        session()->forget(self::KEY);
    }

    /**
     * The step to go back to after this Essentials lesson, or null when the
     * learner did not arrive from a lesson step (or can no longer open it).
     *
     * @return array{lesson: Lesson, block: Block}|null
     */
    public function returnTarget(User $user, ?Lesson $essentials = null): ?array
    {
        $trip = session()->get(self::KEY);

        if (! is_array($trip) || ! is_int($trip['lesson'] ?? null) || ! is_int($trip['block'] ?? null)) {
            return null;
        }

        if ($essentials !== null && ($trip['essentials'] ?? null) !== $essentials->id) {
            return null;
        }

        $lesson = Lesson::query()->find($trip['lesson']);
        $block = $lesson?->visibleBlocks()->whereKey($trip['block'])->first();

        if ($lesson === null || $block === null || ! $user->can('view', $lesson)) {
            return null;
        }

        return ['lesson' => $lesson, 'block' => $block];
    }

    /**
     * Where "Back to your lesson" points, with the lesson's title.
     *
     * @return array{url: string, title: string}|null
     */
    public function returnLink(User $user, Lesson $essentials): ?array
    {
        $target = $this->returnTarget($user, $essentials);

        if ($target === null) {
            return null;
        }

        return [
            'url' => route('learn.essentials.return'),
            'title' => $target['lesson']->title,
        ];
    }

    public function stepUrl(Lesson $lesson, Block $block): string
    {
        return $this->navigator->stepUrl($lesson, $block);
    }
}

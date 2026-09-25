<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Http\Requests\Learn\AnswerActivityRequest;
use App\Models\ActivityPlacement;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Learning\ActivityPresenter;
use App\Services\Learning\AttemptRecorder;
use App\Services\Learning\BlockPresenter;
use App\Services\Learning\LessonNavigator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One practice activity inside a lesson step (PRAC-01..07, TEST-06,
 * DATA-01, PROG-03; spec 0003 B.9, Part E).
 *
 * `{placement}` is route-scoped to `{block}`. An answer is scored and
 * written as an `attempts` row, then the page is shown again with `result`
 * carried over the redirect, so the URL stays the activity's own and a
 * refresh never re-posts an answer (PROG-04).
 */
class ActivityController extends Controller
{
    private const RESULT_KEY = 'learn.activity.result';

    public function __construct(
        private readonly ActivityPresenter $activities,
        private readonly BlockPresenter $blocks,
        private readonly LessonNavigator $navigator,
        private readonly AttemptRecorder $recorder,
    ) {}

    public function show(Request $request, Lesson $lesson, Block $block, ActivityPlacement $placement): Response
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('view', $lesson);
        abort_unless($block->is_visible, 404);

        $result = $request->session()->get(self::RESULT_KEY);
        $result = is_array($result) && ($result['placementId'] ?? null) === $placement->id ? $result : null;

        return Inertia::render('employee/lesson/Activity', [
            'lesson' => $this->blocks->lesson($lesson),
            'steps' => $this->navigator->steps($user, $lesson, $block),
            'block' => [
                'id' => $block->id,
                'type' => $block->type->value,
                'heading' => $block->heading(),
                'url' => $this->navigator->stepUrl($lesson, $block),
                'activityNumber' => $placement->position,
            ],
            'activity' => $this->activities->present($placement, $user, ActivityPresenter::MODE_PRACTICE),
            'backUrl' => $this->navigator->stepUrl($lesson, $block),
            'answerUrl' => route('learn.lessons.activity.answer', ['lesson' => $lesson, 'block' => $block, 'placement' => $placement]),
            'result' => $result,
        ]);
    }

    public function answer(AnswerActivityRequest $request, Lesson $lesson, Block $block, ActivityPlacement $placement): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('view', $lesson);
        abort_unless($block->is_visible, 404);

        $activity = $placement->activity()->firstOrFail();

        if (! $activity->allowsUnlimitedAttempts()
            && $this->activities->attemptsUsed($placement, $user) >= $activity->attempts_allowed) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('You have used all the attempts for this activity.'),
            ]);

            return to_route('learn.lessons.activity', ['lesson' => $lesson, 'block' => $block, 'placement' => $placement]);
        }

        $attempt = $this->recorder->record(
            $user,
            $lesson,
            $block,
            $placement,
            $request->answers(),
            $request->startedAt(),
        );

        return to_route('learn.lessons.activity', ['lesson' => $lesson, 'block' => $block, 'placement' => $placement])
            ->with(self::RESULT_KEY, [
                'placementId' => $placement->id,
                ...$this->recorder->resultFor($attempt),
            ]);
    }
}

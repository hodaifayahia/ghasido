<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Http\Requests\Learn\AnswerActivityRequest;
use App\Models\ActivityPlacement;
use App\Models\Attempt;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Learning\ActivityPresenter;
use App\Services\Learning\AttemptHistory;
use App\Services\Learning\AttemptRecorder;
use App\Services\Learning\BlockPresenter;
use App\Services\Learning\LessonNavigator;
use App\Services\Meaning\HelperMeaningSwap;
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
        private readonly AttemptHistory $history,
    ) {}

    public function show(Request $request, Lesson $lesson, Block $block, ActivityPlacement $placement): Response
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('view', $lesson);
        abort_unless($block->is_visible, 404);

        $result = $request->session()->get(self::RESULT_KEY);
        $result = is_array($result) && ($result['placementId'] ?? null) === $placement->id ? $result : null;

        // The page polls a spoken or written answer while it is judged
        // (PERF-04), and the attempt history opens an earlier try: `?attempt=`
        // rebuilds the result of the learner's own answer to this placement.
        // Another learner's attempt is a 403 (ROLE-02, SEC-01).
        $attemptId = (int) $request->query('attempt', 0);

        if ($attemptId > 0) {
            $attempt = Attempt::query()
                ->whereKey($attemptId)
                ->where('placement_id', $placement->id)
                ->whereNull('test_attempt_id')
                ->first();

            if ($attempt !== null) {
                Gate::authorize('view', $attempt);
            }

            $result = $attempt === null ? null : [
                'placementId' => $placement->id,
                ...$this->recorder->resultFor($attempt),
            ];
        } elseif ($result === null) {
            // Reopened later (after a logout, on another device): the latest
            // saved answer with its result and feedback comes back from the
            // server, never from the browser (PROG-01, PROG-04; client
            // report 2026-11-05). "Try again" starts a new attempt.
            $latest = $this->history->latestPractice($user, $placement);

            $result = $latest === null ? null : [
                'placementId' => $placement->id,
                ...$this->recorder->resultFor($latest),
            ];
        }

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
            'activity' => app(HelperMeaningSwap::class)->apply($this->activities->present($placement, $user, ActivityPresenter::MODE_PRACTICE, $lesson->accent), $user),
            'backUrl' => $this->navigator->stepUrl($lesson, $block),
            'answerUrl' => route('learn.lessons.activity.answer', ['lesson' => $lesson, 'block' => $block, 'placement' => $placement]),
            'result' => $result,
            // Every earlier try, newest first, to compare (client report
            // 2026-11-05).
            'attempts' => $this->history->practice($user, $lesson, $block, $placement),
        ]);
    }

    public function answer(AnswerActivityRequest $request, Lesson $lesson, Block $block, ActivityPlacement $placement): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('view', $lesson);
        abort_unless($block->is_visible, 404);

        // A retried request of a submission already saved shows that
        // attempt again instead of writing (or refusing) a second one.
        $repeat = $this->recorder->submitted($user, $request->submissionKey());

        if ($repeat !== null && $repeat->placement_id === $placement->id) {
            return to_route('learn.lessons.activity', ['lesson' => $lesson, 'block' => $block, 'placement' => $placement])
                ->with(self::RESULT_KEY, [
                    'placementId' => $placement->id,
                    ...$this->recorder->resultFor($repeat),
                ]);
        }

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
            $request->submissionKey(),
        );

        return to_route('learn.lessons.activity', ['lesson' => $lesson, 'block' => $block, 'placement' => $placement])
            ->with(self::RESULT_KEY, [
                'placementId' => $placement->id,
                ...$this->recorder->resultFor($attempt),
            ]);
    }
}

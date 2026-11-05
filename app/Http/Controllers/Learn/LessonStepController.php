<?php

namespace App\Http\Controllers\Learn;

use App\Enums\BlockType;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Learning\AttemptHistory;
use App\Services\Learning\BlockPresenter;
use App\Services\Learning\EssentialsDetour;
use App\Services\Learning\LessonNavigator;
use App\Services\Learning\ProgressService;
use App\Services\Meaning\HelperMeaningSwap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One lesson step (LESSON-01..04, PROG-01, PROG-03, JOURNEY-01; spec 0003
 * Part E).
 *
 * `{block}` is route-scoped to `{lesson}`, so a block of another lesson is a
 * 404 before this runs. LessonPolicy::view refuses a lesson outside the
 * learner's reach or before the Pre-test with a 403, never a redirect.
 */
class LessonStepController extends Controller
{
    public function __construct(
        private readonly BlockPresenter $presenter,
        private readonly LessonNavigator $navigator,
        private readonly ProgressService $progress,
        private readonly EssentialsDetour $detour,
        private readonly AttemptHistory $history,
    ) {}

    public function show(Request $request, Lesson $lesson, Block $block): Response
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('view', $lesson);
        abort_unless($block->is_visible, 404);

        $neighbours = $this->navigator->neighbours($lesson, $block);

        return Inertia::render('employee/lesson/Step', [
            'lesson' => $this->presenter->lesson($lesson),
            'steps' => $this->navigator->steps($user, $lesson, $block),
            // Inline meanings in the learner's helper language (client
            // request 2026-09-30).
            'block' => app(HelperMeaningSwap::class)->apply($this->presenter->present($block, $user), $user),
            'prevUrl' => $neighbours['prev'] === null ? null : $this->navigator->stepUrl($lesson, $neighbours['prev']),
            'nextUrl' => $neighbours['next'] === null ? null : $this->navigator->stepUrl($lesson, $neighbours['next']),
            'completeUrl' => route('learn.lessons.step.complete', ['lesson' => $lesson, 'block' => $block]),
            // English Essentials (client request 2026-10-18): the help link a
            // department step offers, and inside an Essentials lesson the
            // way back to the step the learner came from.
            'help' => $this->helpLink($user, $lesson, $block),
            'returnTo' => $lesson->isEssentials() ? $this->detour->returnLink($user, $lesson) : null,
            'exitUrl' => $lesson->isEssentials()
                ? route('learn.essentials.show', ['course' => $lesson->course_id])
                : route('learn.home'),
            // The learner's saved pronunciation checks on a Listen & Repeat
            // step, so the last result and every earlier try come back after
            // a logout (PROG-01, DATA-02; client report 2026-11-05).
            'pronunciationHistory' => $block->type === BlockType::ListenRepeat
                ? $this->history->pronunciation($user, $block)
                : [],
        ]);
    }

    /**
     * The Essentials lesson this step points at, when the learner may open
     * it; never shown inside an Essentials lesson itself.
     *
     * @return array{prompt: string, title: string, url: string}|null
     */
    private function helpLink(User $user, Lesson $lesson, Block $block): ?array
    {
        if ($block->help_lesson_id === null || $lesson->isEssentials()) {
            return null;
        }

        $target = $block->helpLesson()->first();

        if ($target === null || ! $target->isEssentials() || ! $user->can('view', $target)) {
            return null;
        }

        return [
            'prompt' => $block->help_prompt ?? __('Need help with this? Learn it in English Essentials.'),
            'title' => $target->title,
            'url' => route('learn.lessons.step.essentials', ['lesson' => $lesson, 'block' => $block]),
        ];
    }

    /**
     * Mark the step done and move on (PROG-03).
     *
     * Idempotent: a retried request writes nothing new. The last step sends
     * the learner home; every other step to the next one.
     */
    public function complete(Request $request, Lesson $lesson, Block $block): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('view', $lesson);
        abort_unless($block->is_visible, 404);

        $this->progress->completeBlock($user, $lesson, $block);

        $next = $this->navigator->neighbours($lesson, $block)['next'];

        if ($next === null && $lesson->isEssentials()) {
            // The end of an Essentials lesson goes back to the step the
            // learner came from (through the route that forgets the trip),
            // else to its topic. The Lesson Completed screen records itself
            // with a background request: that one must not end the trip,
            // or its "Back to your lesson" button would lose its target.
            if (! $request->wantsJson() && $this->detour->returnTarget($user, $lesson) !== null) {
                return to_route('learn.essentials.return');
            }

            return to_route('learn.essentials.show', ['course' => $lesson->course_id]);
        }

        if ($next === null) {
            return to_route('learn.home');
        }

        return redirect()->to($this->navigator->stepUrl($lesson, $next));
    }
}

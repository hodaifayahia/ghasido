<?php

namespace App\Http\Controllers\Admin\Lessons;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Learning\BlockPresenter;
use App\Services\Learning\LessonNavigator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Preview as employee" (CMS-03): the same step page the learner gets,
 * rendered from the same presenter, but every link points back into the
 * preview and nothing is written — no completion, no attempt, no phrasebook
 * row. Loaded inside the Preview tab's phone and desktop frames.
 */
class LessonPreviewController extends Controller
{
    public function __construct(
        private readonly BlockPresenter $presenter,
        private readonly LessonNavigator $navigator,
    ) {}

    public function show(Request $request, Lesson $lesson, ?Block $block = null): Response
    {
        Gate::authorize('view', $lesson);

        /** @var User $user */
        $user = $request->user();

        $blocks = $lesson->visibleBlocks()->get()->values();
        $block ??= $blocks->first();

        // A lesson with no visible step has nothing to preview; the tab says
        // so before it loads the frame.
        abort_if($block === null, 404);

        abort_unless($block->lesson_id === $lesson->id, 404);

        $neighbours = $this->navigator->neighbours($lesson, $block);
        $steps = $this->navigator->steps($user, $lesson, $block);

        foreach ($steps as $index => $step) {
            $steps[$index]['url'] = route('lessons.preview', ['lesson' => $lesson, 'block' => $blocks[$index]]);
            $steps[$index]['done'] = false;
        }

        return Inertia::render('employee/lesson/Step', [
            'lesson' => $this->presenter->lesson($lesson),
            'steps' => $steps,
            'block' => $this->presenter->present($block, $user),
            'prevUrl' => $neighbours['prev'] === null ? null : route('lessons.preview', ['lesson' => $lesson, 'block' => $neighbours['prev']]),
            'nextUrl' => $neighbours['next'] === null ? null : route('lessons.preview', ['lesson' => $lesson, 'block' => $neighbours['next']]),
            'completeUrl' => route('lessons.preview.next', ['lesson' => $lesson, 'block' => $block]),
            'preview' => true,
        ]);
    }

    /**
     * "Next" inside the preview: move on without recording anything.
     */
    public function next(Lesson $lesson, Block $block): RedirectResponse
    {
        Gate::authorize('view', $lesson);
        abort_unless($block->lesson_id === $lesson->id, 404);

        $next = $this->navigator->neighbours($lesson, $block)['next'];

        return redirect()->to(route('lessons.preview', $next === null
            ? ['lesson' => $lesson]
            : ['lesson' => $lesson, 'block' => $next]));
    }
}

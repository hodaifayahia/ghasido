<?php

namespace App\Http\Controllers\Learn;

use App\Enums\EssentialsIcon;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Learning\CourseOutline;
use App\Services\Learning\EssentialsCatalogue;
use App\Services\Learning\EssentialsDetour;
use App\Services\Learning\JourneyService;
use App\Services\Learning\LessonNavigator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * English Essentials for the learner (client request 2026-10-18): the topic
 * list, one topic's lessons, the help link from a department lesson step
 * into an Essentials lesson, and the way back to that same step.
 *
 * Visibility is Course::essentialsFor / Lesson::reachableBy (ROLE-02); a
 * topic or lesson outside the learner's reach is a 403. Lessons keep the
 * same Pre-test gate as the department training (JOURNEY-01), through
 * LessonPolicy::view.
 */
class EssentialsController extends Controller
{
    public function __construct(
        private readonly EssentialsCatalogue $catalogue,
        private readonly CourseOutline $outline,
        private readonly EssentialsDetour $detour,
        private readonly JourneyService $journey,
        private readonly LessonNavigator $navigator,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('employee/Essentials', [
            'topics' => $this->catalogue->rows($user, $this->navigator->completedLessonIds($user)),
            'locked' => ! $this->journey->lessonsUnlocked($user),
        ]);
    }

    public function show(Request $request, Course $course): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(Course::query()->whereKey($course->id)->essentialsFor($user)->exists(), 403);

        $course->load(CourseOutline::with());

        return Inertia::render('employee/EssentialsTopic', [
            'course' => $this->outline->present(
                $course,
                $this->navigator->completedLessonIds($user),
                ! $this->journey->lessonsUnlocked($user),
            ),
            'icon' => ($course->icon ?? EssentialsIcon::Sprout)->value,
        ]);
    }

    /**
     * "Want to learn this first? Tap here": from a department lesson step
     * to the Essentials lesson its admin linked, remembering the step so the
     * learner comes back to it afterwards.
     */
    public function help(Request $request, Lesson $lesson, Block $block): RedirectResponse
    {
        Gate::authorize('view', $lesson);
        abort_unless($block->is_visible, 404);

        $target = $block->helpLesson()->first();

        abort_if($target === null || ! $target->isEssentials(), 404);
        Gate::authorize('view', $target);

        /** @var User $user */
        $user = $request->user();
        $entry = $this->navigator->entryBlock($user, $target);

        abort_if($entry === null, 404);

        $this->detour->start($lesson, $block, $target);

        return redirect()->to($this->navigator->stepUrl($target, $entry));
    }

    /**
     * Back to the lesson step the learner left from, or My Lessons when
     * there is none any more.
     */
    public function back(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $target = $this->detour->returnTarget($user);
        $this->detour->forget();

        if ($target === null) {
            return to_route('learn.essentials.index');
        }

        return redirect()->to($this->navigator->stepUrl($target['lesson'], $target['block']));
    }
}

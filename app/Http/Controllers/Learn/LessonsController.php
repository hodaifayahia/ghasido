<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
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
 * My Lessons (JOURNEY-01, JOURNEY-03, LESSON-04, PROG-02; spec 0003 Part E).
 *
 * The employee's own curriculum only, through Course::forLearner (ROLE-02).
 * Every lesson card carries `locked` until the Pre-test is submitted; the
 * lock is repeated server side by LessonPolicy on `show`, so a locked card
 * with a reachable route cannot exist (JOURNEY-01).
 *
 * Below the department training sits the English Essentials card (client
 * request 2026-10-18): its own topics and progress, outside the journey.
 */
class LessonsController extends Controller
{
    public function __construct(
        private readonly JourneyService $journey,
        private readonly LessonNavigator $navigator,
        private readonly CourseOutline $outline,
        private readonly EssentialsCatalogue $essentials,
        private readonly EssentialsDetour $detour,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $locked = ! $this->journey->lessonsUnlocked($user);
        $completed = $this->navigator->completedLessonIds($user);

        $courses = Course::query()
            ->forLearner($user)
            ->orderBy('position')
            ->orderBy('id')
            ->with(CourseOutline::with())
            ->get();

        $essentials = $this->essentials->summary($user, $completed);

        return Inertia::render('employee/Lessons', [
            // A published course with no published lesson yet stays off My
            // Lessons (client request 2026-11-05).
            'courses' => $courses
                ->map(fn (Course $course): array => $this->outline->present($course, $completed, $locked))
                ->filter(fn (array $course): bool => $course['units'] !== [])
                ->values()
                ->all(),
            'essentials' => $essentials === null ? null : $essentials + ['locked' => $locked],
            'journey' => $this->journey->summary($user),
        ]);
    }

    /**
     * Opening a lesson lands on its first uncompleted step (LESSON-04).
     */
    public function show(Request $request, Lesson $lesson): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('view', $lesson);

        // Opened from My Lessons, not from a lesson step's help link: there
        // is no lesson to go back to.
        if ($lesson->isEssentials()) {
            $this->detour->forget();
        }

        $block = $this->navigator->entryBlock($user, $lesson);

        abort_if($block === null, 404);

        return redirect()->to($this->navigator->stepUrl($lesson, $block));
    }
}

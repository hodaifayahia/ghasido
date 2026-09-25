<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Unit;
use App\Models\User;
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
 */
class LessonsController extends Controller
{
    public function __construct(
        private readonly JourneyService $journey,
        private readonly LessonNavigator $navigator,
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
            ->with(['units' => fn ($units) => $units->with(['lessons' => fn ($lessons) => $lessons->published()->withCount('visibleBlocks')])])
            ->get();

        return Inertia::render('employee/Lessons', [
            'courses' => $courses->map(fn (Course $course): array => [
                'id' => $course->id,
                'title' => $course->title,
                'description' => $course->description,
                'tone' => $course->tone,
                'units' => $course->units
                    ->filter(fn (Unit $unit): bool => $unit->isPublished())
                    ->map(fn (Unit $unit): array => [
                        'id' => $unit->id,
                        'title' => $unit->title,
                        'lessons' => $unit->lessons->map(fn (Lesson $lesson): array => [
                            'id' => $lesson->id,
                            'title' => $lesson->title,
                            'position' => $lesson->position,
                            'estimatedMinutes' => $lesson->estimated_minutes,
                            'stepCount' => (int) ($lesson->visible_blocks_count ?? 0),
                            'completed' => in_array($lesson->id, $completed, true),
                            'locked' => $locked,
                            'url' => route('learn.lessons.show', ['lesson' => $lesson]),
                        ])->values()->all(),
                    ])->values()->all(),
            ])->values()->all(),
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

        $block = $this->navigator->entryBlock($user, $lesson);

        abort_if($block === null, 404);

        return redirect()->to($this->navigator->stepUrl($lesson, $block));
    }
}

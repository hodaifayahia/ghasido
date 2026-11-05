<?php

namespace App\Http\Controllers\Admin\Essentials;

use App\Enums\CourseTrack;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Essentials\ReorderEssentialsRequest;
use App\Http\Requests\Admin\Essentials\StoreEssentialsLessonRequest;
use App\Http\Requests\Admin\Essentials\StoreEssentialsTopicRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Content\LessonService;
use App\Services\Essentials\EssentialsStudio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The English Essentials studio (client request 2026-11-05): a screen of
 * its own for the beginner topics, apart from the department lesson
 * builder. Topics and their lessons are listed flat (no units); a lesson
 * is added from a beginner template and its steps are then filled in the
 * usual lesson editor (LESSON-02, BLD-07). Show/hide, delete and publish
 * reuse the existing course and lesson routes.
 */
class EssentialsStudioController extends Controller
{
    public function index(Request $request, EssentialsStudio $studio): Response
    {
        Gate::authorize('viewAny', Course::class);

        /** @var User $user */
        $user = $request->user('web');

        return Inertia::render('admin/EnglishEssentials', $studio->page($user, (int) $request->query('topic', 0)));
    }

    public function storeTopic(StoreEssentialsTopicRequest $request, LessonService $lessons): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user('web');

        $topic = $lessons->createCourse([
            'title' => (string) $request->validated('title'),
            'description' => $request->validated('description'),
            'department_id' => null,
            // A hotel admin's topic belongs to their hotel; the platform
            // team's is shared (CMS-04, ORG-04).
            'hotel_id' => $user->hotel_id,
            'tone' => (string) ($request->validated('tone') ?? 'brand'),
            'track' => CourseTrack::Essentials->value,
            'icon' => (string) $request->validated('icon'),
            'level' => null,
        ], $user, $request->audience());
        $lessons->createUnit($topic, 'Unit 1');

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':title was created. It stays hidden from employees until you show it.', ['title' => $topic->title])]);

        return to_route('essentials-studio', ['topic' => $topic->id]);
    }

    public function storeLesson(StoreEssentialsLessonRequest $request, Course $course, LessonService $lessons): RedirectResponse
    {
        $lesson = $lessons->createEssentialsLesson($course, (string) $request->validated('title'), $request->template());

        if ($request->boolean('open_editor')) {
            return to_route('lessons.edit', ['lesson' => $lesson]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':title was added as a draft.', ['title' => $lesson->title])]);

        return to_route('essentials-studio', ['topic' => $course->id]);
    }

    public function reorderLessons(ReorderEssentialsRequest $request, Course $course, LessonService $lessons): RedirectResponse
    {
        /** @var list<int> $known */
        $known = Lesson::query()->where('course_id', $course->id)->notArchived()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $order = array_values(array_intersect($request->order(), $known));

        $lessons->reorderEssentialsLessons($course, $order);

        return to_route('essentials-studio', ['topic' => $course->id]);
    }

    public function reorderTopics(ReorderEssentialsRequest $request, LessonService $lessons): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user('web');

        /** @var list<int> $known */
        $known = Course::query()->essentials()->notArchived()->sharedOrFor($user->hotel_id)
            ->get(['id', 'hotel_id'])
            ->filter(fn (Course $topic): bool => $user->can('update', $topic))
            ->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $lessons->reorderEssentialsTopics(array_values(array_intersect($request->order(), $known)));

        return back();
    }
}

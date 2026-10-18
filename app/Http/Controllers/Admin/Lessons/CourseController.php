<?php

namespace App\Http\Controllers\Admin\Lessons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lessons\StoreCourseRequest;
use App\Http\Requests\Admin\Lessons\UpdateCourseRequest;
use App\Models\Course;
use App\Models\User;
use App\Services\Content\ContentTree;
use App\Services\Content\LessonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Courses (CMS-01, CMS-04, ORG-04). The form requests authorize through
 * CoursePolicy; LessonService writes with the audit row.
 */
class CourseController extends Controller
{
    public function store(StoreCourseRequest $request, LessonService $lessons): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $course = $lessons->createCourse($request->courseData(), $user, $request->audience());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':title was created.', ['title' => $course->title])]);

        return to_route('lessons-content', [
            'hotel' => $course->hotel_id === null ? 'shared' : $course->hotel_id,
            'department' => ContentTree::departmentKey($course),
            'course' => $course->id,
        ]);
    }

    public function update(UpdateCourseRequest $request, Course $course, LessonService $lessons): RedirectResponse
    {
        $lessons->updateCourse($course, $request->courseData());

        $audience = $request->audience();

        if ($audience !== null) {
            $lessons->setAudience($course, $audience);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':title was updated.', ['title' => $course->title])]);

        return back();
    }

    /**
     * Copy the course with its units and lessons (client request 2026-10-05).
     */
    public function duplicate(Request $request, Course $course, LessonService $lessons): RedirectResponse
    {
        Gate::authorize('duplicate', $course);

        /** @var User $user */
        $user = $request->user();
        $copy = $lessons->duplicateCourse($course, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':title was copied. The copy is a draft: rename it and change what you need.', ['title' => $course->title])]);

        return to_route('lessons-content', [
            'hotel' => $copy->hotel_id === null ? 'shared' : $copy->hotel_id,
            'department' => ContentTree::departmentKey($copy),
            'course' => $copy->id,
        ]);
    }

    /**
     * "Delete" (client request 2026-10-05, CMS-01). Learner answers are
     * never deleted: a course holding any is removed and kept for reports
     * (DATA-10). Authorized here, not by hiding the button (SEC-01).
     */
    public function destroy(Course $course, LessonService $lessons): RedirectResponse
    {
        Gate::authorize('destroy', $course);

        $title = $course->title;
        $target = [
            'hotel' => $course->hotel_id === null ? 'shared' : $course->hotel_id,
            'department' => ContentTree::departmentKey($course),
        ];
        $deleted = $lessons->deleteCourse($course);

        Inertia::flash('toast', $deleted
            ? ['type' => 'success', 'message' => __(':title was deleted.', ['title' => $title])]
            : ['type' => 'success', 'message' => __(':title was removed from the library. Learner answers were kept for reports.', ['title' => $title])]);

        return to_route('lessons-content', $target);
    }

    public function archive(Course $course, LessonService $lessons): RedirectResponse
    {
        Gate::authorize('delete', $course);

        $lessons->archiveCourse($course);

        Inertia::flash('toast', ['type' => 'info', 'message' => __(':title was archived. Nothing was deleted.', ['title' => $course->title])]);

        return back();
    }
}

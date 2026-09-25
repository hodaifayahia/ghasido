<?php

namespace App\Http\Controllers\Admin\Lessons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lessons\StoreCourseRequest;
use App\Http\Requests\Admin\Lessons\UpdateCourseRequest;
use App\Models\Course;
use App\Models\User;
use App\Services\Content\LessonService;
use Illuminate\Http\RedirectResponse;
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
        $course = $lessons->createCourse($request->courseData(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':title was created.', ['title' => $course->title])]);

        return to_route('lessons-content', [
            'hotel' => $course->hotel_id === null ? 'shared' : $course->hotel_id,
            'department' => $course->department_id,
            'course' => $course->id,
        ]);
    }

    public function update(UpdateCourseRequest $request, Course $course, LessonService $lessons): RedirectResponse
    {
        $lessons->updateCourse($course, $request->courseData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':title was updated.', ['title' => $course->title])]);

        return back();
    }

    public function archive(Course $course, LessonService $lessons): RedirectResponse
    {
        Gate::authorize('delete', $course);

        $lessons->archiveCourse($course);

        Inertia::flash('toast', ['type' => 'info', 'message' => __(':title was archived. Nothing was deleted.', ['title' => $course->title])]);

        return back();
    }
}

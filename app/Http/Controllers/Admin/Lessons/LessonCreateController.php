<?php

namespace App\Http\Controllers\Admin\Lessons;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Department;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The second step of creating a lesson (CMS-01, LESSON-02). The department is
 * chosen in the directory modal; this page chooses the existing course/unit
 * and the lesson details before the lesson is written.
 */
class LessonCreateController extends Controller
{
    public function create(Request $request): Response
    {
        Gate::authorize('create', Lesson::class);

        /** @var User $user */
        $user = $request->user();

        $departments = Department::query()
            ->active()
            ->visibleTo($user)
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name']);

        $requestedDepartment = (int) $request->query('department', 0);
        $department = $departments->firstWhere('id', $requestedDepartment)
            ?? $departments->first();

        $courses = $department === null
            ? collect()
            : Course::query()
                ->where('department_id', $department->id)
                ->sharedOrFor($user->hotel_id)
                ->with(['units' => fn ($units) => $units
                    ->orderBy('position')
                    ->orderBy('id')])
                ->orderBy('position')
                ->orderBy('id')
                ->get();

        return Inertia::render('admin/LessonsCreate', [
            'departments' => $departments->map(fn (Department $row): array => [
                'value' => (string) $row->id,
                'label' => $row->name,
            ])->values()->all(),
            'departmentId' => $department?->id === null ? null : (string) $department->id,
            'departmentName' => $department?->name,
            'courses' => $courses->map(fn (Course $course): array => [
                'id' => $course->id,
                'title' => $course->title,
                'units' => $course->units->map(fn ($unit): array => [
                    'id' => $unit->id,
                    'title' => $unit->title,
                ])->values()->all(),
            ])->values()->all(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin\Lessons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lessons\StoreUnitRequest;
use App\Http\Requests\Admin\Lessons\UpdateUnitRequest;
use App\Models\Unit;
use App\Services\Content\ContentTree;
use App\Services\Content\LessonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Units inside a course (CMS-01). Authorized through the course's policy.
 */
class UnitController extends Controller
{
    public function store(StoreUnitRequest $request, LessonService $lessons): RedirectResponse
    {
        $course = $request->course();
        $unit = $lessons->createUnit($course, $request->title());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':title was added.', ['title' => $unit->title])]);

        return to_route('lessons-content', [
            'hotel' => $course->hotel_id === null ? 'shared' : $course->hotel_id,
            'department' => ContentTree::departmentKey($course),
            'course' => $course->id,
            'unit' => $unit->id,
            // Stay in the builder: the new row has no lesson yet.
            'builder' => 1,
        ]);
    }

    public function update(UpdateUnitRequest $request, Unit $unit, LessonService $lessons): RedirectResponse
    {
        $lessons->updateUnit($unit, $request->unitData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':title was updated.', ['title' => $unit->title])]);

        return back();
    }

    /**
     * Copy the unit with its lessons into the same course (client request
     * 2026-10-05).
     */
    public function duplicate(Unit $unit, LessonService $lessons): RedirectResponse
    {
        $course = $unit->course()->firstOrFail();
        Gate::authorize('duplicate', $course);

        $copy = $lessons->duplicateUnit($unit);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':title was copied. The copy is a draft: rename it and change what you need.', ['title' => $unit->title])]);

        return to_route('lessons-content', [
            'hotel' => $course->hotel_id === null ? 'shared' : $course->hotel_id,
            'department' => ContentTree::departmentKey($course),
            'course' => $course->id,
            'unit' => $copy->id,
            // Stay in the builder: the new row has no lesson yet.
            'builder' => 1,
        ]);
    }

    /**
     * "Delete" (client request 2026-10-05, CMS-01): a unit holding learner
     * answers is removed and kept for reports (DATA-10).
     */
    public function destroy(Unit $unit, LessonService $lessons): RedirectResponse
    {
        $course = $unit->course()->firstOrFail();
        Gate::authorize('destroy', $course);

        $title = $unit->title;
        $deleted = $lessons->deleteUnit($unit);

        Inertia::flash('toast', $deleted
            ? ['type' => 'success', 'message' => __(':title was deleted.', ['title' => $title])]
            : ['type' => 'success', 'message' => __(':title was removed from the library. Learner answers were kept for reports.', ['title' => $title])]);

        return to_route('lessons-content', LessonsContentController::builderQuery($course));
    }
}

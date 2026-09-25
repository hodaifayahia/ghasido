<?php

namespace App\Http\Controllers\Admin\Lessons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lessons\StoreUnitRequest;
use App\Http\Requests\Admin\Lessons\UpdateUnitRequest;
use App\Models\Unit;
use App\Services\Content\LessonService;
use Illuminate\Http\RedirectResponse;
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
            'department' => $course->department_id,
            'course' => $course->id,
            'unit' => $unit->id,
        ]);
    }

    public function update(UpdateUnitRequest $request, Unit $unit, LessonService $lessons): RedirectResponse
    {
        $lessons->updateUnit($unit, $request->unitData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':title was updated.', ['title' => $unit->title])]);

        return back();
    }
}

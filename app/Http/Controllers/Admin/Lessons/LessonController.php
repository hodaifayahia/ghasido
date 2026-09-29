<?php

namespace App\Http\Controllers\Admin\Lessons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lessons\StoreLessonRequest;
use App\Http\Requests\Admin\Lessons\UpdateLessonRequest;
use App\Models\Lesson;
use App\Services\Content\LessonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Lessons: create, autosave, publish, duplicate, archive (CMS-01, CMS-05,
 * LESSON-02, BLD-07, BLD-08). LessonPolicy authorizes; LessonService writes.
 */
class LessonController extends Controller
{
    public function store(StoreLessonRequest $request, LessonService $lessons): RedirectResponse
    {
        $lesson = DB::transaction(function () use ($request, $lessons) {
            $unit = $request->unit($lessons);

            return $lessons->createLesson($unit, $request->title(), $request->withDefaultBlocks());
        });
        $unit = $lesson->unit()->firstOrFail();
        $course = $unit->course()->firstOrFail();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':title was created as a draft.', ['title' => $lesson->title])]);

        return to_route('lessons-content', [
            'hotel' => $course->hotel_id === null ? 'shared' : $course->hotel_id,
            'department' => $course->department_id,
            'course' => $course->id,
            'unit' => $unit->id,
            'lesson' => $lesson->id,
        ]);
    }

    /**
     * The editor autosaves one field at a time (BLD-08); no toast for those,
     * the page shows a "Saved" hint instead. A full Settings save toasts.
     */
    public function update(UpdateLessonRequest $request, Lesson $lesson, LessonService $lessons): RedirectResponse
    {
        $data = $request->lessonData();
        $lessons->updateLesson($lesson, $data);

        if ($request->boolean('_notify')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Lesson saved.')]);
        }

        return back();
    }

    public function publish(Lesson $lesson, LessonService $lessons): RedirectResponse
    {
        Gate::authorize('publish', $lesson);

        if ($lesson->isPublished()) {
            $lessons->unpublish($lesson);
            Inertia::flash('toast', ['type' => 'info', 'message' => __(':title is back in draft.', ['title' => $lesson->title])]);
        } else {
            $cascade = $lessons->publish($lesson);
            $course = $lesson->course()->with('department')->first();
            $audience = __(':department — :scope', [
                'department' => $course?->department->name ?? __('its department'),
                'scope' => $lesson->hotel_id === null ? __('all hotels') : ($lesson->hotel()->value('name') ?? __('one hotel')),
            ]);

            $message = match (true) {
                $cascade['course'] && $cascade['unit'] => __(':title is published, together with its unit and its course ":course". Visible to: :audience.', ['title' => $lesson->title, 'course' => $course->title ?? '', 'audience' => $audience]),
                $cascade['course'] => __(':title is published, together with its course ":course". Visible to: :audience.', ['title' => $lesson->title, 'course' => $course->title ?? '', 'audience' => $audience]),
                $cascade['unit'] => __(':title is published, together with its unit. Visible to: :audience.', ['title' => $lesson->title, 'audience' => $audience]),
                default => __(':title is published. Visible to: :audience.', ['title' => $lesson->title, 'audience' => $audience]),
            };

            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
        }

        return back();
    }

    public function duplicate(Lesson $lesson, LessonService $lessons): RedirectResponse
    {
        Gate::authorize('update', $lesson);

        $copy = $lessons->duplicate($lesson);
        $course = $copy->course()->firstOrFail();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':title was duplicated.', ['title' => $lesson->title])]);

        return to_route('lessons-content', [
            'hotel' => $course->hotel_id === null ? 'shared' : $course->hotel_id,
            'department' => $course->department_id,
            'course' => $course->id,
            'unit' => $copy->unit_id,
            'lesson' => $copy->id,
        ]);
    }

    public function archive(Lesson $lesson, LessonService $lessons): RedirectResponse
    {
        Gate::authorize('delete', $lesson);

        $lessons->archiveLesson($lesson);

        Inertia::flash('toast', ['type' => 'info', 'message' => __(':title was archived. Nothing was deleted.', ['title' => $lesson->title])]);

        return back();
    }
}

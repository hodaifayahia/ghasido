<?php

namespace App\Http\Controllers\Admin\Lessons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lessons\StoreActivityRequest;
use App\Http\Requests\Admin\Lessons\UpdateActivityRequest;
use App\Models\Activity;
use App\Models\User;
use App\Services\Content\ActivityService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Practice activities (PRAC-01..07, WRITE-05, DATA-11, TEST-09).
 * ActivityPolicy authorizes through the form requests; ActivityService
 * writes and the model versions.
 */
class ActivityController extends Controller
{
    public function store(StoreActivityRequest $request, ActivityService $activities): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $activity = $activities->create($request->activityData(), $user, $request->block());

        // Name the activity the way the builder lists it: its title, or its type.
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':label was added.', ['label' => $activity->title ?? $activity->type->label()])]);

        return back();
    }

    public function update(UpdateActivityRequest $request, Activity $activity, ActivityService $activities): RedirectResponse
    {
        $activities->update($activity, $request->activityData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':label was saved (version :version).', [
                'label' => $activity->title ?? $activity->type->label(),
                'version' => $activity->current_version,
            ]),
        ]);

        return back();
    }
}

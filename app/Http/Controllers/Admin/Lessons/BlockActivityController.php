<?php

namespace App\Http\Controllers\Admin\Lessons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lessons\ReorderBlockActivitiesRequest;
use App\Models\ActivityPlacement;
use App\Models\Block;
use App\Services\Content\ActivityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use InvalidArgumentException;

/**
 * The activities of a Practice block and the questions of a Quiz block
 * (PRAC-01..07, BLD-03; client report 2026-09-29): reorder and remove.
 * Creating and editing go through ActivityController (activities.store with
 * `block_id`, activities.update), so one `activities` table serves lessons
 * and tests alike and every content edit is a new version (PRAC-05,
 * DATA-11). `{placement}` is route-scoped to `{block}`.
 */
class BlockActivityController extends Controller
{
    public function reorder(ReorderBlockActivitiesRequest $request, Block $block, ActivityService $activities): RedirectResponse
    {
        try {
            $activities->reorder($block, $request->order());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['order' => $exception->getMessage()]);
        }

        return back();
    }

    public function destroy(Block $block, ActivityPlacement $placement, ActivityService $activities): RedirectResponse
    {
        Gate::authorize('update', $block);

        $activity = $placement->activity()->first();
        $label = $activity === null ? __('Activity') : ($activity->title ?? $activity->type->label());
        $kept = $activities->remove($placement);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $kept
                ? __('":label" was removed from :block. Learners’ answers are kept.', ['label' => $label, 'block' => $block->heading()])
                : __('":label" was deleted from :block.', ['label' => $label, 'block' => $block->heading()]),
        ]);

        return back();
    }
}

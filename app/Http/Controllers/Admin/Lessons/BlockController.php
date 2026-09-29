<?php

namespace App\Http\Controllers\Admin\Lessons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lessons\AssignLessonScenariosRequest;
use App\Http\Requests\Admin\Lessons\ReorderBlocksRequest;
use App\Http\Requests\Admin\Lessons\StoreBlockRequest;
use App\Http\Requests\Admin\Lessons\UpdateBlockRequest;
use App\Models\Block;
use App\Models\Lesson;
use App\Services\Content\BlockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use InvalidArgumentException;

/**
 * A lesson's blocks: add, edit, reorder, duplicate, hide, delete
 * (BLD-02, BLD-03, BLD-07, LESSON-02). BlockPolicy defers to the lesson.
 */
class BlockController extends Controller
{
    public function store(StoreBlockRequest $request, Lesson $lesson, BlockService $blocks): RedirectResponse
    {
        $block = $blocks->add($lesson, $request->type(), $request->title(), $request->position());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':block was added to the lesson.', ['block' => $block->heading()])]);

        return back();
    }

    public function update(UpdateBlockRequest $request, Block $block, BlockService $blocks): RedirectResponse
    {
        $blocks->update($block, $request->blockData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':block was saved.', ['block' => $block->heading()])]);

        return back();
    }

    /**
     * The "AI Role-play" tab's picker: set the lesson's scenarios in one go,
     * adding a role-play step when the lesson has none (RP-01).
     */
    public function scenarios(AssignLessonScenariosRequest $request, Lesson $lesson, BlockService $blocks): RedirectResponse
    {
        $blocks->assignScenarios($lesson, $request->scenarioIds());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The lesson\'s role-play scenarios were saved.')]);

        return back();
    }

    public function reorder(ReorderBlocksRequest $request, Lesson $lesson, BlockService $blocks): RedirectResponse
    {
        try {
            $blocks->reorder($lesson, $request->order());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['order' => $exception->getMessage()]);
        }

        return back();
    }

    public function duplicate(Block $block, BlockService $blocks): RedirectResponse
    {
        Gate::authorize('update', $block);

        $blocks->duplicate($block);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':block was duplicated.', ['block' => $block->heading()])]);

        return back();
    }

    public function toggle(Block $block, BlockService $blocks): RedirectResponse
    {
        Gate::authorize('update', $block);

        $blocks->toggle($block);

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => $block->is_visible
                ? __(':block is visible to learners again.', ['block' => $block->heading()])
                : __(':block is hidden from learners.', ['block' => $block->heading()]),
        ]);

        return back();
    }

    public function destroy(Block $block, BlockService $blocks): RedirectResponse
    {
        Gate::authorize('delete', $block);

        $heading = $block->heading();
        $blocks->destroy($block);

        Inertia::flash('toast', ['type' => 'info', 'message' => __(':block was removed from the lesson.', ['block' => $heading])]);

        return back();
    }
}

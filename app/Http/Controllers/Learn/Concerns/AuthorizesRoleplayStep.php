<?php

namespace App\Http\Controllers\Learn\Concerns;

use App\Models\AiScenario;
use App\Models\Block;
use App\Models\Lesson;
use Illuminate\Support\Facades\Gate;

/**
 * A role-play is started from a lesson step, so the step itself is checked
 * as strictly as the lesson player checks it (spec 0005 §1.3).
 *
 * The lesson must be one the learner may open, which carries gate 1 of the
 * journey (JOURNEY-01) and the department and hotel reach (ROLE-02); the
 * block must belong to that lesson and be visible; and the block must offer
 * the scenario. A foreign lesson is a 403, a block or scenario that does not
 * belong to it a 404, the same answers the scoped lesson routes give.
 */
trait AuthorizesRoleplayStep
{
    private function authorizeRoleplayStep(Lesson $lesson, Block $block, AiScenario $scenario): void
    {
        Gate::authorize('view', $lesson);

        abort_unless($block->lesson_id === $lesson->id && $block->is_visible, 404);
        abort_unless(in_array($scenario->id, $block->scenarioIds(), true), 404);
    }
}

<?php

namespace App\Services\Content;

use App\Enums\BlockType;
use App\Models\AiScenario;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\Lesson;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Every write to a lesson's blocks (BLD-02, BLD-03, BLD-07, LESSON-02;
 * spec 0003 B.10). One transaction and one audit row per change (SEC-06).
 */
class BlockService
{
    public function __construct(private readonly LessonService $lessons) {}

    /**
     * Add a block of a type at the end of the lesson (or before the closing
     * "complete" step, so the lesson always ends on it), with its default
     * settings so the learner page renders straight away.
     */
    public function add(Lesson $lesson, BlockType $type, ?string $title = null, ?int $position = null): Block
    {
        return DB::transaction(function () use ($lesson, $type, $title, $position): Block {
            $blocks = $lesson->blocks()->get()->values();
            $insertAt = $position ?? $this->defaultInsertPosition($blocks->all(), $type);

            $block = new Block;
            $block->lesson_id = $lesson->id;
            $block->type = $type;
            $block->title = $title;
            $block->layout = 'full';
            $block->settings = BlockDefaults::settings($type);
            $block->is_visible = true;
            $block->position = $insertAt;
            $block->save();

            $this->renumber($lesson, $block->id, $insertAt);

            AuditLog::record($block, 'block.created', ['created' => ['type' => $type->value, 'lesson_id' => $lesson->id, 'position' => $insertAt]]);

            return $block;
        });
    }

    /**
     * Save a block editor (BLD-03). Settings are replaced whole: the editor
     * always posts the block's complete contract for its type.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Block $block, array $data): Block
    {
        return DB::transaction(function () use ($block, $data): Block {
            $scenarioIds = null;

            if (array_key_exists('title', $data)) {
                $block->title = is_string($data['title']) && trim($data['title']) !== '' ? $data['title'] : null;
            }

            if (isset($data['layout']) && is_string($data['layout'])) {
                $block->layout = $data['layout'];
            }

            if (isset($data['settings']) && is_array($data['settings'])) {
                $settings = $data['settings'];

                if (isset($data['scenario_ids']) && is_array($data['scenario_ids'])) {
                    $settings['scenario_ids'] = array_values(array_map('intval', $data['scenario_ids']));
                    $scenarioIds = $settings['scenario_ids'];
                }

                if ($scenarioIds === null && is_array($settings['scenario_ids'] ?? null)) {
                    $scenarioIds = array_values(array_map('intval', $settings['scenario_ids']));
                }

                $block->settings = $settings;
            } elseif (isset($data['scenario_ids']) && is_array($data['scenario_ids'])) {
                $settings = $block->settings ?? [];
                $settings['scenario_ids'] = array_values(array_map('intval', $data['scenario_ids']));
                $block->settings = $settings;
                $scenarioIds = $settings['scenario_ids'];
            }

            if ($scenarioIds === null && is_array($block->setting('scenario_ids'))) {
                $scenarioIds = array_values(array_map('intval', $block->setting('scenario_ids')));
            }

            AuditLog::record($block, 'block.updated', array_filter([
                'lexicon_item_ids' => $data['lexicon_item_ids'] ?? null,
                'activity_ids' => $data['activity_ids'] ?? null,
                'scenario_ids' => $scenarioIds,
            ]));
            $block->save();

            if ($block->type === BlockType::AiRoleplay && $scenarioIds !== null) {
                $this->syncScenarios($block, $scenarioIds);
            }

            if (isset($data['lexicon_item_ids']) && is_array($data['lexicon_item_ids'])) {
                $this->syncLexicon($block, array_values(array_map('intval', $data['lexicon_item_ids'])));
            }

            if (isset($data['activity_ids']) && is_array($data['activity_ids'])) {
                $this->syncActivities($block, array_values(array_map('intval', $data['activity_ids'])));
            }

            return $block;
        });
    }

    /**
     * Attach only scenarios that belong to the lesson's department and hotel
     * scope. The client picker is filtered too, but this server-side check is
     * the security boundary (ROLE-02, SEC-01, TSTM-05).
     *
     * @param  list<int>  $ids
     */
    public function syncScenarios(Block $block, array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $lesson = $block->lesson()->with('course')->firstOrFail();
        $course = $lesson->course;

        if ($course === null) {
            throw ValidationException::withMessages([
                'scenario_ids' => __('This lesson is not attached to a course yet.'),
            ]);
        }

        $allowed = AiScenario::query()
            ->notArchived()
            ->whereIn('id', $ids)
            ->where('department_id', $course->department_id)
            ->where(function ($query) use ($lesson): void {
                $query->whereNull('hotel_id');

                if ($lesson->hotel_id !== null) {
                    $query->orWhere('hotel_id', $lesson->hotel_id);
                }
            })
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if (count($allowed) !== count($ids)) {
            throw ValidationException::withMessages([
                'scenario_ids' => __('Select scenarios from the lesson department and hotel scope.'),
            ]);
        }

        $pivot = [];
        foreach ($ids as $index => $scenarioId) {
            $pivot[$scenarioId] = ['position' => $index + 1];
        }

        $block->scenarios()->sync($pivot);
    }

    /**
     * @param  list<int>  $ids
     */
    public function syncLexicon(Block $block, array $ids): void
    {
        $sync = [];
        foreach ($ids as $index => $id) {
            $sync[$id] = ['position' => $index + 1];
        }

        $block->lexiconItems()->sync($sync);
    }

    /**
     * Placements are kept, reordered or removed by activity id; a removed
     * placement's attempts still reference the activity version (DATA-11).
     *
     * @param  list<int>  $ids
     */
    public function syncActivities(Block $block, array $ids): void
    {
        $existing = $block->placements()->get()->keyBy('activity_id');

        foreach ($ids as $index => $activityId) {
            $placement = $existing->get($activityId);

            if ($placement === null) {
                $block->placements()->create(['activity_id' => $activityId, 'position' => $index + 1]);

                continue;
            }

            $placement->position = $index + 1;
            $placement->save();
        }

        $block->placements()->whereNotIn('activity_id', $ids)->delete();
    }

    /**
     * @param  list<int>  $order  every block id of the lesson, in the new order
     */
    public function reorder(Lesson $lesson, array $order): void
    {
        DB::transaction(function () use ($lesson, $order): void {
            $ids = $lesson->blocks()->pluck('id')->all();

            if (array_diff($order, $ids) !== [] || count($order) !== count($ids)) {
                throw new InvalidArgumentException('The order must list every block of this lesson exactly once.');
            }

            foreach ($order as $index => $id) {
                Block::query()->whereKey($id)->update(['position' => $index + 1]);
            }

            AuditLog::record($lesson, 'blocks.reordered', ['order' => $order]);
        });
    }

    public function duplicate(Block $block): Block
    {
        return DB::transaction(function () use ($block): Block {
            $copy = $block->replicate();
            $copy->position = $block->position + 1;
            $copy->save();

            $lesson = $block->lesson()->firstOrFail();
            $this->renumber($lesson, $copy->id, $block->position + 1);
            $this->lessons->reattach($block, $copy);

            AuditLog::record($copy, 'block.duplicated', ['source_block_id' => $block->id]);

            return $copy;
        });
    }

    public function toggle(Block $block): Block
    {
        return DB::transaction(function () use ($block): Block {
            $block->is_visible = ! $block->is_visible;
            AuditLog::record($block, $block->is_visible ? 'block.shown' : 'block.hidden');
            $block->save();

            return $block;
        });
    }

    /**
     * Delete a block. Attempts made on its activities are not touched: they
     * reference the activity version, not the block (DATA-10, DATA-11).
     */
    public function destroy(Block $block): void
    {
        DB::transaction(function () use ($block): void {
            $lesson = $block->lesson()->firstOrFail();

            AuditLog::record($lesson, 'block.deleted', [
                'block' => ['id' => $block->id, 'type' => $block->type->value, 'title' => $block->title],
            ]);

            $block->lexiconItems()->detach();
            $block->placements()->delete();
            $block->delete();

            $this->renumber($lesson);
        });
    }

    // ------------------------------------------------------------- helpers

    /**
     * @param  array<int, Block>  $blocks
     */
    private function defaultInsertPosition(array $blocks, BlockType $type): int
    {
        $last = end($blocks);

        if ($last instanceof Block && $last->type === BlockType::Complete && $type !== BlockType::Complete) {
            return $last->position;
        }

        return ($last instanceof Block ? $last->position : 0) + 1;
    }

    /**
     * Positions 1..n with no gaps; the block given keeps its slot.
     */
    private function renumber(Lesson $lesson, ?int $keepId = null, ?int $keepAt = null): void
    {
        $blocks = $lesson->blocks()->get()->values()->all();

        if ($keepId !== null && $keepAt !== null) {
            $kept = null;
            $rest = [];
            foreach ($blocks as $block) {
                if ($block->id === $keepId) {
                    $kept = $block;
                } else {
                    $rest[] = $block;
                }
            }

            if ($kept !== null) {
                array_splice($rest, max(0, $keepAt - 1), 0, [$kept]);
                $blocks = $rest;
            }
        }

        foreach ($blocks as $index => $block) {
            if ($block->position !== $index + 1) {
                Block::query()->whereKey($block->id)->update(['position' => $index + 1]);
            }
        }
    }
}

<?php

namespace App\Services\Content;

use App\Enums\ContentStatus;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\Attempt;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Practice and test activities (PRAC-01..07, WRITE-05, DATA-11, TEST-09;
 * spec 0003 B.9). The model writes a new activity_versions row whenever the
 * payload or scoring changes, so an attempt always points at the version it
 * answered; this service never touches an existing version.
 */
class ActivityService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?Block $block = null): Activity
    {
        return DB::transaction(function () use ($data, $actor, $block): Activity {
            $activity = new Activity;
            $activity->fill($data);
            $activity->status ??= ContentStatus::Published;
            $activity->attempts_allowed = (int) ($data['attempts_allowed'] ?? 0);
            $activity->show_meaning_enabled = (bool) ($data['show_meaning_enabled'] ?? true);
            $activity->created_by = $actor->id;

            if ($block !== null) {
                $this->scopeToBlock($activity, $block);
            }

            $activity->save();

            AuditLog::record($activity, 'activity.created', [
                'created' => ['type' => $activity->type->value, 'prompt' => $activity->prompt, 'version' => $activity->current_version],
            ]);

            if ($block !== null) {
                $this->place($block, $activity);
            }

            return $activity;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Activity $activity, array $data): Activity
    {
        return DB::transaction(function () use ($activity, $data): Activity {
            $before = $activity->current_version;
            $activity->fill($data);
            AuditLog::record($activity, 'activity.updated', ['version_before' => $before]);
            $activity->save();

            return $activity;
        });
    }

    /**
     * Place an activity at the end of a block (idempotent).
     */
    public function place(Block $block, Activity $activity): void
    {
        if ($block->placements()->where('activity_id', $activity->id)->exists()) {
            return;
        }

        $block->placements()->create([
            'activity_id' => $activity->id,
            'position' => ((int) $block->placements()->max('position')) + 1,
        ]);
    }

    /**
     * Reorder a block's activities (BLD-03). `$placementIds` lists every
     * placement of the block exactly once, in the new order.
     *
     * @param  list<int>  $placementIds
     */
    public function reorder(Block $block, array $placementIds): void
    {
        DB::transaction(function () use ($block, $placementIds): void {
            $ids = $block->placements()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

            if (count($placementIds) !== count($ids) || array_diff($placementIds, $ids) !== []) {
                throw new InvalidArgumentException(__('The order must list every activity of this block exactly once.'));
            }

            foreach ($placementIds as $index => $id) {
                ActivityPlacement::query()->whereKey($id)->update(['position' => $index + 1]);
            }

            AuditLog::record($block, 'block.activities.reordered', ['order' => $placementIds]);
        });
    }

    /**
     * Take an activity out of a block (BLD-03).
     *
     * Learner answers are never deleted (DATA-10): attempts keep their
     * activity version, block and lesson, and only lose the placement link,
     * exactly as when a whole block is removed. The activity itself is
     * deleted only when nobody ever answered it and nothing else places it;
     * otherwise it is kept, unplaced, with every version its answers point
     * at (DATA-11).
     *
     * @return bool whether the activity row was kept because it holds answers or other placements
     */
    public function remove(ActivityPlacement $placement): bool
    {
        return DB::transaction(function () use ($placement): bool {
            $block = $placement->placeable;
            $activity = $placement->activity()->first();

            $placement->delete();

            if ($block instanceof Block) {
                $position = 1;

                foreach ($block->placements()->get() as $remaining) {
                    if ($remaining->position !== $position) {
                        ActivityPlacement::query()->whereKey($remaining->id)->update(['position' => $position]);
                    }

                    $position++;
                }
            }

            $kept = $activity !== null && (
                Attempt::query()->where('activity_id', $activity->id)->exists()
                || $activity->placements()->exists()
            );

            AuditLog::record($block instanceof Block ? $block : $placement, 'block.activity.removed', [
                'activity_id' => $placement->activity_id,
                'placement_id' => $placement->id,
                'activity_kept' => $kept,
            ]);

            if ($activity !== null && ! $kept) {
                $activity->delete();
            }

            return $kept;
        });
    }

    /**
     * An activity authored inside a lesson belongs to that lesson's hotel
     * and department unless the request said otherwise (ROLE-02, CMS-04).
     */
    private function scopeToBlock(Activity $activity, Block $block): void
    {
        $lesson = $block->lesson()->with('course')->first();

        if ($lesson === null) {
            return;
        }

        $activity->hotel_id ??= $lesson->hotel_id;
        $activity->department_id ??= $lesson->course?->department_id;
    }
}

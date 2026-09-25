<?php

namespace App\Services\Content;

use App\Enums\ContentStatus;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
}

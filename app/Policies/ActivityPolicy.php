<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Activity;
use App\Models\User;

/**
 * One activity serves lessons and tests alike (PRAC-05, WRITE-05); it is
 * shared unless pinned to a hotel (ROLE-02, SEC-01). Editing writes a new
 * version, never the old one (DATA-11, TEST-09) — that is the model's job;
 * here is only who may ask. The Super Admin never reaches here.
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::LessonsView->value);
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->can(Permission::LessonsView->value) && $this->isInReach($user, $activity);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::LessonsManage->value);
    }

    public function update(User $user, Activity $activity): bool
    {
        return $user->can(Permission::LessonsManage->value) && $this->isInReach($user, $activity);
    }

    private function isInReach(User $user, Activity $activity): bool
    {
        return $activity->hotel_id === null || $activity->hotel_id === $user->hotel_id;
    }
}

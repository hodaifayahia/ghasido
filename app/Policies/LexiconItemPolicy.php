<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\LexiconItem;
use App\Models\User;

/**
 * Vocabulary and expressions are shared content (CMS-06) unless pinned to a
 * hotel; an admin-side reader sees shared rows and their own hotel's
 * (ROLE-02, SEC-01). The Super Admin never reaches here.
 */
class LexiconItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::LessonsView->value);
    }

    public function view(User $user, LexiconItem $item): bool
    {
        return $user->can(Permission::LessonsView->value) && $this->isInReach($user, $item);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::LessonsManage->value);
    }

    public function update(User $user, LexiconItem $item): bool
    {
        return $user->can(Permission::LessonsManage->value) && $this->isInReach($user, $item);
    }

    private function isInReach(User $user, LexiconItem $item): bool
    {
        return $item->hotel_id === null || $item->hotel_id === $user->hotel_id;
    }
}

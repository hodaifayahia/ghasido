<?php

namespace App\Policies;

use App\Models\PhrasebookItem;
use App\Models\User;

/**
 * A phrasebook is personal (PHRASE-01, ROLE-02, spec 0003 Part E).
 *
 * Owner only: viewing and removing an entry are the two things a learner does
 * with it (PHRASE-05).
 */
class PhrasebookItemPolicy
{
    public function view(User $user, PhrasebookItem $item): bool
    {
        return $this->owns($user, $item);
    }

    public function delete(User $user, PhrasebookItem $item): bool
    {
        return $this->owns($user, $item);
    }

    private function owns(User $user, PhrasebookItem $item): bool
    {
        return $item->user_id === $user->id;
    }
}

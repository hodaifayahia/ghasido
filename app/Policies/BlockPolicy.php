<?php

namespace App\Policies;

use App\Models\Block;
use App\Models\User;

/**
 * A block is only ever touched through its lesson, so every answer is the
 * lesson's (BLD-03; ROLE-02, SEC-01). The Super Admin never reaches here.
 */
class BlockPolicy
{
    public function view(User $user, Block $block): bool
    {
        return $user->can('view', $block->lesson()->firstOrFail());
    }

    public function update(User $user, Block $block): bool
    {
        return $user->can('update', $block->lesson()->firstOrFail());
    }

    public function delete(User $user, Block $block): bool
    {
        return $this->update($user, $block);
    }
}

<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ContentGeneration;
use App\Models\User;

/**
 * Who may watch or retry a "Generate with AI" request (ROLE-02, SEC-01;
 * spec 0004): the admin who asked for it. The Super Admin passes through
 * Gate::before (ROLE-03).
 */
class ContentGenerationPolicy
{
    public function view(User $user, ContentGeneration $generation): bool
    {
        return $user->can(Permission::LessonsManage->value) && $generation->user_id === $user->id;
    }

    public function retry(User $user, ContentGeneration $generation): bool
    {
        return $this->view($user, $generation);
    }
}

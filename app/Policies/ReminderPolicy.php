<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Reminder;
use App\Models\User;

/**
 * Who may read the reminder log and press Send (REM-01, REM-06, REM-07,
 * ROLE-02, SEC-01; spec 0003 Part D).
 *
 * A manager holds both messaging capabilities for their own hotel only. The
 * hotel boundary on a batch send is enforced in ReminderBatchService, which
 * refuses (403) any recipient outside the actor's hotel; the log query is
 * scoped in MessagesDirectory. The Super Admin passes through Gate::before
 * (ROLE-03).
 */
class ReminderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MessagesView->value);
    }

    public function view(User $user, Reminder $reminder): bool
    {
        return $user->can(Permission::MessagesView->value)
            && $this->reaches($user, $reminder);
    }

    /**
     * Send a reminder to employees the actor may address.
     */
    public function send(User $user): bool
    {
        return $user->can(Permission::MessagesManage->value);
    }

    private function reaches(User $user, Reminder $reminder): bool
    {
        if ($user->hasRole(Role::SuperAdmin->value)) {
            return true;
        }

        $recipient = $reminder->user;

        return $recipient !== null
            && $user->hotel_id !== null
            && $recipient->hotel_id === $user->hotel_id;
    }
}

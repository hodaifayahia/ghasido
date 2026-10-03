<?php

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Lesson;
use App\Models\User;

/**
 * Who may open or change a lesson (ROLE-01, ROLE-02, JOURNEY-01, SEC-01,
 * spec 0003 Part E).
 *
 * `view` is the learner gate and answers two questions in order: is this
 * lesson visible to this learner at all (published, their department, shared
 * or their hotel), and have they submitted the Pre-test. A locked looking
 * card with a reachable route is a bug, so the route must refuse here, not
 * in the UI (JOURNEY-01). A refusal is a 403, never a 404, because the
 * cross-tenant test asserts exactly that.
 *
 * The Super Admin never reaches any of this: Gate::before in
 * AppServiceProvider returns true for them first (ROLE-03).
 */
class LessonPolicy
{
    /**
     * Admin listing (the Lessons & Content screen).
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::LessonsView->value);
    }

    /**
     * A learner opening a lesson, or an admin reading it.
     */
    public function view(User $user, Lesson $lesson): bool
    {
        if ($user->can(Permission::LessonsView->value)) {
            // Someone who reads lessons but cannot edit them (a manager)
            // sees published lessons only (client report 2026-10-02).
            if (! $user->can(Permission::LessonsManage->value) && $lesson->status !== ContentStatus::Published) {
                return false;
            }

            return $this->isInReach($user, $lesson);
        }

        // Gate 1 of the employee journey: no lesson before the Pre-test is
        // submitted (JOURNEY-01) — when a published Pre-test exists for the
        // learner; with none, lessons are open (client decision 2026-09-23).
        // Visibility is checked first so a lesson outside the learner's
        // reach is refused for that reason too.
        return $lesson->isVisibleTo($user) && $user->lessonsUnlocked();
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::LessonsManage->value);
    }

    /**
     * Bulk audio crosses hotel boundaries, so it is a platform-owner action
     * rather than a normal lesson write (ROLE-03, PRIV-04).
     */
    public function generateAllAudio(User $user): bool
    {
        return $user->hasRole(Role::SuperAdmin->value);
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $user->can(Permission::LessonsManage->value) && $this->isInReach($user, $lesson);
    }

    public function publish(User $user, Lesson $lesson): bool
    {
        return $this->update($user, $lesson);
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $this->update($user, $lesson);
    }

    /**
     * "Delete" from the directory table (CMS-01). Shared content reaches
     * every hotel, so only a platform level admin (no hotel of their own)
     * may delete it; a hotel admin deletes their own hotel's rows only
     * (ROLE-02). Learner answers are never deleted either way (DATA-10).
     */
    public function destroy(User $user, Lesson $lesson): bool
    {
        if (! $this->update($user, $lesson)) {
            return false;
        }

        return $lesson->hotel_id !== null || $user->hotel_id === null;
    }

    /**
     * An admin side reader may see shared content and their own hotel's, never
     * another hotel's (ROLE-02). A user with no hotel sees the shared rows.
     */
    private function isInReach(User $user, Lesson $lesson): bool
    {
        return $lesson->hotel_id === null || $lesson->hotel_id === $user->hotel_id;
    }
}

<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Course;
use App\Models\User;

/**
 * Who may read or change a course, and through it its units (ROLE-01,
 * ROLE-02, CMS-01, SEC-01; spec 0003 Part D).
 *
 * Two questions, in order: does the user hold the capability, and is this
 * course within their reach (shared, or their own hotel's). A refusal is a
 * 403, never a 404. The Super Admin never reaches here (Gate::before).
 */
class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::LessonsView->value);
    }

    public function view(User $user, Course $course): bool
    {
        return $user->can(Permission::LessonsView->value) && $this->isInReach($user, $course);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::LessonsManage->value);
    }

    public function update(User $user, Course $course): bool
    {
        return $user->can(Permission::LessonsManage->value) && $this->isInReach($user, $course);
    }

    public function delete(User $user, Course $course): bool
    {
        return $this->update($user, $course);
    }

    private function isInReach(User $user, Course $course): bool
    {
        return $course->hotel_id === null || $course->hotel_id === $user->hotel_id;
    }
}

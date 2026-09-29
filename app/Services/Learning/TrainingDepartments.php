<?php

namespace App\Services\Learning;

use App\Enums\Role;
use App\Models\Course;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The departments a manager may train in (client decision 2026-09-23).
 *
 * A manager has no department of their own, so before they can work through
 * the training they pick one. The choice is limited to active departments the
 * manager may see (the shared catalogue plus their own hotel's) that actually
 * carry a published course for their hotel, so a manager never lands on an
 * empty curriculum. The same list validates the session value in
 * ResolveTrainingDepartment, so a manager cannot train in a department that
 * is not theirs (ROLE-02, SEC-01).
 *
 * An individual subscriber with several departments (owner request
 * 2026-09-25) switches the same way, between exactly the departments on
 * their subscription.
 */
class TrainingDepartments
{
    /**
     * @return Collection<int, Department>
     */
    public function availableFor(User $user): Collection
    {
        if ($user->isIndividual()) {
            $ids = $user->individualSubscription?->departmentIds() ?? [];

            return Department::query()
                ->active()
                ->whereIn('id', $ids)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return Department::query()
            ->active()
            ->visibleTo($user)
            ->whereHas('courses', function (Builder $course) use ($user): void {
                /** @var Builder<Course> $course */
                $course->published()->sharedOrFor($user->hotel_id);
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Whether this user switches between departments: a manager (who has
     * none of their own) or an individual subscriber with more than one.
     */
    public function switches(User $user): bool
    {
        if ($user->isIndividual()) {
            return count($user->individualSubscription?->departmentIds() ?? []) > 1;
        }

        return $user->department_id === null && $user->hasRole(Role::Manager->value);
    }

    /**
     * Whether this manager may train in the given department.
     */
    public function canTrainIn(User $user, int $departmentId): bool
    {
        return $this->availableFor($user)->contains('id', $departmentId);
    }

    /**
     * The chooser options and the switcher's list.
     *
     * @return list<array{id: int, name: string}>
     */
    public function options(User $user): array
    {
        return array_values(
            $this->availableFor($user)
                ->map(fn (Department $department): array => [
                    'id' => $department->id,
                    'name' => $department->name,
                ])
                ->all(),
        );
    }
}

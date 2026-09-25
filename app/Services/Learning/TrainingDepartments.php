<?php

namespace App\Services\Learning;

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
 */
class TrainingDepartments
{
    /**
     * @return Collection<int, Department>
     */
    public function availableFor(User $user): Collection
    {
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

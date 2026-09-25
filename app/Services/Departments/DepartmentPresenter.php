<?php

namespace App\Services\Departments;

use App\Enums\CapacityState;
use App\Models\Department;
use App\Models\SeatQuota;

/**
 * Shapes one department into the two payloads resources/js/types/departments.ts
 * calls DepartmentRecord and DepartmentOverview. Shapes only: every figure
 * comes from a model method, and the row carries the counts it was eager
 * loaded with (Department::scopeWithDirectoryCounts).
 */
class DepartmentPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function row(Department $department, int $rank): array
    {
        return [
            'id' => $department->id,
            // The paginator's `from` plus the row's position, never stored.
            'rank' => $rank,
            'name' => $department->name,
            'focus' => $department->focus ?? '',
            'scope' => $department->isShared() ? 'shared' : 'hotel',
            'scopeLabel' => $this->scopeLabel($department),
            'hotelId' => $department->hotel_id,
            'hotelCount' => $department->hotelCount(),
            'employeeCount' => $department->usedSeats(),
            'usedSeats' => $department->usedSeats(),
            'totalSeats' => $department->allowedSeats(),
            'lessonCount' => $department->publishedLessonsCount(),
            'testCount' => $department->publishedTestsCount(),
            'scenarioCount' => $department->publishedScenariosCount(),
            'status' => $department->status->value,
            'isActive' => $department->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(Department $department): array
    {
        $quotas = $department->seatQuotasWithUsage();

        return [
            ...$this->row($department, 0),
            'notes' => $this->notes($department, $quotas->all()),
            'assignments' => array_map(
                static fn (SeatQuota $quota): array => [
                    'hotelId' => $quota->hotel_id,
                    'hotel' => $quota->hotel->name,
                    'manager' => $quota->hotel->manager_name,
                    'usedSeats' => $quota->usedSeats(),
                    'totalSeats' => $quota->allowed_seats,
                    'state' => $quota->capacityState()->value,
                ],
                $quotas->all(),
            ),
        ];
    }

    /**
     * "Shared Across Hotels" or "<Hotel> only", as the mockup writes them.
     * The hotel name is read without the tenant scope: the label names the
     * owner whoever is looking.
     */
    private function scopeLabel(Department $department): string
    {
        if ($department->isShared()) {
            return __('Shared Across Hotels');
        }

        $hotelName = $department->hotel()->withoutGlobalScopes()->value('name');

        return __(':hotel only', ['hotel' => is_string($hotelName) ? $hotelName : __('Hotel')]);
    }

    /**
     * The Key Notes sentences, each generated from data (spec 0003 Part D):
     * how many hotels draw on the department, which of them are at or over
     * their seat quota, and whether the Pre-test and Post-test are paired.
     *
     * @param  array<int, SeatQuota>  $quotas
     * @return list<string>
     */
    private function notes(Department $department, array $quotas): array
    {
        $notes = [];
        $hotels = count($quotas);

        if ($department->isShared()) {
            $notes[] = match ($hotels) {
                0 => __('Shared template not assigned to any hotel yet.'),
                1 => __('Shared template currently powers 1 hotel team.'),
                default => __('Shared template currently powers :count hotel teams.', ['count' => $hotels]),
            };
        } else {
            $notes[] = $hotels === 0
                ? __('Hotel-specific department with no seat quota yet.')
                : __('Hotel-specific department with :seats allowed seats.', ['seats' => $department->allowedSeats()]);
        }

        $limited = array_values(array_filter(
            $quotas,
            static fn (SeatQuota $quota): bool => $quota->capacityState() !== CapacityState::Available,
        ));

        if ($limited !== []) {
            $names = array_map(
                static fn (SeatQuota $quota): string => $quota->hotel->name,
                $limited,
            );

            $notes[] = count($names) === 1
                ? __(':hotel has reached its :department seat quota.', [
                    'hotel' => $names[0],
                    'department' => mb_strtolower($department->name),
                ])
                : __(':hotels have reached their :department seat quotas.', [
                    'hotels' => $this->joinNames($names),
                    'department' => mb_strtolower($department->name),
                ]);
        }

        $notes[] = $department->hasPairedTests()
            ? __('Pre-test and post-test are already paired for this department.')
            : __('Pre-test and post-test are not paired for this department yet.');

        return $notes;
    }

    /**
     * "A, B and C".
     *
     * @param  list<string>  $names
     */
    private function joinNames(array $names): string
    {
        $last = array_pop($names);

        return implode(', ', $names).' '.__('and').' '.$last;
    }
}

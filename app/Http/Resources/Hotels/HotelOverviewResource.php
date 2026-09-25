<?php

namespace App\Http\Resources\Hotels;

use App\Enums\HotelAccessState;
use App\Enums\HotelStatus;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\SeatQuota;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The sidebar for one hotel, in the shape resources/js/types/hotels.ts calls
 * HotelOverview (spec 0002, directory child, steps 6 and 7).
 *
 * The two alert sentences follow the umbrella's Value sourcing table to the
 * letter: renewal only when the derived status is expiring, seat limit only
 * when at least one department is at or over quota.
 *
 * @property Hotel $resource
 */
class HotelOverviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hotel = $this->resource;
        $quotas = $hotel->seatQuotasWithUsage();
        $status = $hotel->derivedStatus();
        $paused = $hotel->access_state === HotelAccessState::Paused;

        return [
            'id' => $hotel->id,
            'name' => $hotel->name,
            'manager' => $hotel->manager_name,
            'email' => $hotel->manager_email,
            'city' => $hotel->city,
            'contractStart' => HotelRowResource::formatDate($hotel->contract_starts_on),
            'contractEnd' => $paused ? __('Paused') : HotelRowResource::formatDate($hotel->contract_ends_on),
            'daysRemaining' => $hotel->daysRemaining(),
            'usedSeats' => $hotel->usedSeats(),
            'totalSeats' => $hotel->allowedSeats(),
            // Employees means active learner accounts: the same count as used
            // seats, by definition (spec 0002, invariant 9).
            'employees' => $hotel->usedSeats(),
            'departments' => $quotas->count(),
            'status' => $status->value,
            'accessState' => $hotel->access_state->value,
            'alerts' => $this->alerts($hotel, $status, $quotas->all()),
            'quotas' => array_map(
                static fn (SeatQuota $quota): array => [
                    'departmentId' => $quota->department_id,
                    'department' => $quota->department->name,
                    'usedSeats' => $quota->usedSeats(),
                    'totalSeats' => $quota->allowed_seats,
                    'state' => $quota->capacityState()->value,
                ],
                $quotas->all(),
            ),
            // Every department the hotel may hold seats in, for Manage seats.
            'seatCatalogue' => $hotel->seatCatalogueWithUsage()
                ->map(static fn (Department $department): array => [
                    'departmentId' => $department->id,
                    'department' => $department->name,
                    'usedSeats' => (int) $department->getAttribute('used_seats_count'),
                    'allowedSeats' => $department->getAttribute('allowed_seats'),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, SeatQuota>  $quotas
     * @return list<string>
     */
    private function alerts(Hotel $hotel, HotelStatus $status, array $quotas): array
    {
        $alerts = [];

        if ($status === HotelStatus::Expiring) {
            $weeks = $hotel->weeksRemaining() ?? 0;

            $alerts[] = $weeks <= 1
                ? __('Renewal follow-up recommended within the next week.')
                : __('Renewal follow-up recommended within the next :weeks weeks.', ['weeks' => $weeks]);
        }

        $limited = array_values(array_filter(
            $quotas,
            static fn (SeatQuota $quota): bool => $quota->isAtOrOverLimit(),
        ));

        if ($limited !== []) {
            $names = array_map(
                static fn (SeatQuota $quota): string => $quota->department->name,
                $limited,
            );

            $alerts[] = count($names) === 1
                ? __(':departments has reached its seat limit.', ['departments' => $names[0]])
                : __(':departments have reached their seat limits.', ['departments' => $this->joinNames($names)]);
        }

        return $alerts;
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

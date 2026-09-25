<?php

namespace App\Http\Resources\Hotels;

use App\Enums\HotelAccessState;
use App\Models\Hotel;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the hotels directory, in the shape resources/js/types/hotels.ts
 * calls HotelRecord (spec 0002, AC-20; directory child, step 2).
 *
 * Shapes only. Every figure comes from a model method, and the row carries
 * the seat counts it was eager loaded with (Hotel::scopeWithSeatCounts).
 *
 * @property Hotel $resource
 */
class HotelRowResource extends JsonResource
{
    public const DATE_FORMAT = 'd M Y';

    public function __construct(Hotel $hotel, private readonly int $rank)
    {
        parent::__construct($hotel);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hotel = $this->resource;
        $paused = $hotel->access_state === HotelAccessState::Paused;

        return [
            'id' => $hotel->id,
            // The paginator's `from` plus the row's position, never stored.
            'rank' => $this->rank,
            'name' => $hotel->name,
            'manager' => $hotel->manager_name,
            'email' => $hotel->manager_email,
            'city' => $hotel->city,
            'departments' => $hotel->departmentCount(),
            'usedSeats' => $hotel->usedSeats(),
            'totalSeats' => $hotel->allowedSeats(),
            'contractEnd' => $paused ? __('Paused') : self::formatDate($hotel->contract_ends_on),
            'daysRemaining' => $hotel->daysRemaining(),
            'status' => $hotel->derivedStatus()->value,
            'accessState' => $hotel->access_state->value,
            'capacityState' => $hotel->capacityState()->value,
            // ISO dates for the edit dialog's date inputs.
            'contractStartsOn' => $hotel->contract_starts_on?->toDateString(),
            'contractEndsOn' => $hotel->contract_ends_on?->toDateString(),
        ];
    }

    public static function formatDate(?CarbonInterface $date): string
    {
        return $date === null ? '—' : $date->format(self::DATE_FORMAT);
    }
}

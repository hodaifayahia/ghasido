<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Hotel;
use App\Models\SeatQuota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeatQuota>
 */
class SeatQuotaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'department_id' => Department::factory(),
            'allowed_seats' => fake()->numberBetween(4, 20),
        ];
    }
}

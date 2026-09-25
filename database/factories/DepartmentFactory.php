<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            // Null means the shared catalogue every hotel draws on (ORG-04).
            'hotel_id' => null,
            'position' => 0,
            'is_active' => true,
        ];
    }

    /** A department only this one hotel has. */
    public function forHotel(int $hotelId): static
    {
        return $this->state(fn (array $attributes) => [
            'hotel_id' => $hotelId,
        ]);
    }
}

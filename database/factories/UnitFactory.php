<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Course;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => Str::title(rtrim(fake()->sentence(2), '.')),
            'position' => 0,
            'status' => ContentStatus::Published,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ContentStatus::Draft]);
    }

    public function at(int $position): static
    {
        return $this->state(fn (array $attributes) => ['position' => $position]);
    }
}

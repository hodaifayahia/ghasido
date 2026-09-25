<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\ResultsVisibility;
use App\Enums\TestType;
use App\Models\Department;
use App\Models\Test;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Test>
 */
class TestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => TestType::Pre,
            'department_id' => Department::factory(),
            // Null = shared across hotels (ORG-04).
            'hotel_id' => null,
            'paired_test_id' => null,
            'title' => fake()->sentence(3),
            'intro' => null,
            'settings' => [
                'time_limit_seconds' => 1200,
                'shuffle_questions' => false,
                'results_visibility' => ResultsVisibility::Score->value,
                'pass_score' => null,
                'on_timeout' => 'submit',
            ],
            'status' => ContentStatus::Published,
        ];
    }

    public function pre(): static
    {
        return $this->state(fn (array $attributes) => ['type' => TestType::Pre]);
    }

    public function post(): static
    {
        return $this->state(fn (array $attributes) => ['type' => TestType::Post]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ContentStatus::Draft]);
    }

    /** Belongs to one hotel rather than the shared catalogue. */
    public function forHotel(int $hotelId): static
    {
        return $this->state(fn (array $attributes) => ['hotel_id' => $hotelId]);
    }

    /** No timer at all. */
    public function untimed(): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], ['time_limit_seconds' => null]),
        ]);
    }

    public function withResultsVisibility(ResultsVisibility $visibility): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], ['results_visibility' => $visibility->value]),
        ]);
    }
}

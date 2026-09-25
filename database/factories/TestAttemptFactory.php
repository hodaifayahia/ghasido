<?php

namespace Database\Factories;

use App\Enums\TestAttemptStatus;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestAttempt>
 */
class TestAttemptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employee(),
            'test_id' => Test::factory(),
            'attempt_no' => 1,
            'status' => TestAttemptStatus::InProgress,
            'started_at' => now(),
            'deadline_at' => now()->addSeconds(1200),
            'submitted_at' => null,
            'score' => null,
            'max_score' => null,
            'breakdown' => null,
            'results_released_at' => null,
        ];
    }

    /** Finished and scored. */
    public function submitted(float $score = 18, float $maxScore = 25): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TestAttemptStatus::Submitted,
            'started_at' => now()->subMinutes(15),
            'deadline_at' => now()->addMinutes(5),
            'submitted_at' => now(),
            'score' => $score,
            'max_score' => $maxScore,
            'results_released_at' => now(),
        ]);
    }

    /** The clock ran out before the learner finished. */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TestAttemptStatus::Expired,
            'started_at' => now()->subMinutes(30),
            'deadline_at' => now()->subMinutes(10),
        ]);
    }

    /** No time limit on this sitting. */
    public function untimed(): static
    {
        return $this->state(fn (array $attributes) => ['deadline_at' => null]);
    }
}

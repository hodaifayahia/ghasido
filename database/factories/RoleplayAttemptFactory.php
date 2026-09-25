<?php

namespace Database\Factories;

use App\Enums\RoleplayStatus;
use App\Models\AiScenario;
use App\Models\RoleplayAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoleplayAttempt>
 */
class RoleplayAttemptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employee(),
            'ai_scenario_id' => AiScenario::factory(),
            'lesson_id' => null,
            'block_id' => null,
            'attempt_no' => 1,
            'status' => RoleplayStatus::InProgress,
            'transcript' => [],
            'pending_reply' => false,
            'criteria_scores' => null,
            'overall_score' => null,
            'feedback' => null,
            'ai_status' => null,
            'failed_reason' => null,
            'duration_ms' => null,
            'is_preview' => false,
            'started_at' => now(),
            'ended_at' => null,
        ];
    }

    /**
     * Evaluated, with the mockup's numbers and feedback (spec 0003 G.6).
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RoleplayStatus::Completed,
            'transcript' => [
                ['role' => RoleplayAttempt::ROLE_GUEST, 'text' => 'Hello! I have a reservation for tonight.', 'at' => now()->subMinutes(3)->toIso8601String()],
                ['role' => RoleplayAttempt::ROLE_EMPLOYEE, 'text' => 'Good evening! Welcome. May I have your name, please?', 'at' => now()->subMinutes(2)->toIso8601String()],
                ['role' => RoleplayAttempt::ROLE_GUEST, 'text' => "Yes, it's John Miller.", 'at' => now()->subMinutes(2)->toIso8601String()],
                ['role' => RoleplayAttempt::ROLE_EMPLOYEE, 'text' => 'You are in room fifth floor.', 'at' => now()->subMinute()->toIso8601String()],
            ],
            'criteria_scores' => [
                'pronunciation' => 70,
                'grammar' => 60,
                'vocabulary' => 80,
                'fluency' => 70,
                'politeness' => 80,
            ],
            'overall_score' => 70,
            'feedback' => [
                'summary_label' => 'Good try!',
                'summary_text' => 'Keep practicing. You can try again.',
                'did_well' => [
                    'You greeted the guest politely.',
                    "You asked for the guest's name correctly.",
                    'You gave the breakfast time clearly.',
                    'You used a friendly and professional tone.',
                ],
                'improve' => [
                    ['title' => 'Room information', 'text' => 'The sentence was not clear. Try to use a more natural expression.'],
                ],
                'better_expression' => [
                    'yours' => 'You are in room fifth floor.',
                    'better' => 'Your room is on the fifth floor.',
                ],
                'key_phrase' => '“Your room is on the fifth floor.”',
                'footnote' => 'Use clear and complete sentences. Guests may not understand short or incomplete phrases.',
            ],
            'duration_ms' => 180000,
            'started_at' => now()->subMinutes(3),
            'ended_at' => now(),
        ]);
    }

    public function evaluating(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RoleplayStatus::Evaluating,
            'ended_at' => now(),
        ]);
    }

    public function abandoned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RoleplayStatus::Abandoned,
        ]);
    }

    /** An admin test run that never counts against anyone (RP-13). */
    public function preview(): static
    {
        return $this->state(fn (array $attributes) => ['is_preview' => true]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\AiFeature;
use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsage>
 */
class AiUsageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employee(),
            'hotel_id' => null,
            'feature' => AiFeature::RoleplayTurn,
            'provider' => 'fake',
            'model' => 'fake-1',
            'prompt_tokens' => fake()->numberBetween(50, 400),
            'completion_tokens' => fake()->numberBetween(20, 200),
            'cost_estimate' => 0.000420,
            'occurred_at' => now(),
        ];
    }

    public function forFeature(AiFeature $feature): static
    {
        return $this->state(fn (array $attributes) => ['feature' => $feature]);
    }
}

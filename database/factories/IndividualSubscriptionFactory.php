<?php

namespace Database\Factories;

use App\Models\IndividualSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<IndividualSubscription>
 */
class IndividualSubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'starts_on' => Date::today()->subDays(3)->toDateString(),
            'ends_on' => Date::today()->addDays(60)->toDateString(),
            'ai_enabled' => true,
            'voice_enabled' => true,
            'daily_ai_turns' => null,
            'ai_action_points' => 50,
            'voice_points_per_10_minutes' => 100,
            'price_dzd' => 5000,
            'payment_reference' => null,
            'notes' => null,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn (): array => [
            'starts_on' => Date::today()->subDays(90)->toDateString(),
            'ends_on' => Date::today()->subDay()->toDateString(),
        ]);
    }

    public function withoutAi(): static
    {
        return $this->state(fn (): array => ['ai_enabled' => false]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\HotelAccessState;
use App\Models\Hotel;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Hotel>
 */
class HotelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'city' => fake()->city(),
            'manager_name' => fake()->name(),
            'manager_email' => fake()->unique()->safeEmail(),
            'subscription_plan_id' => SubscriptionPlan::query()->active()->where('slug', 'standard')->value('id')
                ?? SubscriptionPlan::query()->active()->orderBy('employee_limit')->orderBy('id')->value('id'),
            'access_state' => HotelAccessState::Active,
            'contract_starts_on' => now()->subDays(60)->toDateString(),
            'contract_ends_on' => now()->addDays(120)->toDateString(),
        ];
    }

    /** A hotel waiting for the Super Admin to approve it. */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_state' => HotelAccessState::Pending,
        ]);
    }

    /** Comfortably inside its contract: more days left than the warning window. */
    public function active(int $daysRemaining = 120): static
    {
        return $this->state(fn (array $attributes) => [
            'access_state' => HotelAccessState::Active,
            'contract_ends_on' => now()->addDays($daysRemaining)->toDateString(),
        ]);
    }

    /** Inside the warning window, so it reads as Expiring Soon. */
    public function expiring(int $daysRemaining = 11): static
    {
        return $this->active($daysRemaining);
    }

    /** Past its contract end, so it reads as Ended with no state change. */
    public function ended(int $daysAgo = 18): static
    {
        return $this->state(fn (array $attributes) => [
            'access_state' => HotelAccessState::Active,
            'contract_ends_on' => now()->subDays($daysAgo)->toDateString(),
        ]);
    }

    /** Access paused: the clock is frozen, the dates are still real. */
    public function paused(int $pausedDaysAgo = 9): static
    {
        return $this->state(fn (array $attributes) => [
            'access_state' => HotelAccessState::Paused,
            'paused_at' => now()->subDays($pausedDaysAgo),
        ]);
    }

    public function archived(?string $reason = null): static
    {
        return $this->state(fn (array $attributes) => [
            'access_state' => HotelAccessState::Archived,
            'archived_at' => now()->subDays(30),
            'archive_reason' => $reason ?? 'Contract not renewed.',
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\AutomationTrigger;
use App\Models\AutomationRule;
use App\Models\ReminderTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationRule>
 */
class AutomationRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Inactive learner follow-up',
            'trigger' => AutomationTrigger::InactiveDays,
            'days' => 5,
            'template_id' => ReminderTemplate::factory(),
            'audience' => null,
            'audience_label' => 'All hotels',
            'is_active' => true,
            'last_run_at' => null,
        ];
    }

    /** Watch for a different trigger, with its window when it takes one. */
    public function trigger(AutomationTrigger $trigger, ?int $days = null): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger' => $trigger,
            'days' => $days,
            'name' => $trigger->label($days),
        ]);
    }

    /**
     * Limit the rule to some hotels and/or departments.
     *
     * @param  list<int>  $hotelIds
     * @param  list<int>  $departmentIds
     */
    public function audience(array $hotelIds = [], array $departmentIds = []): static
    {
        return $this->state(fn (array $attributes) => [
            'audience' => ['hotel_ids' => $hotelIds, 'department_ids' => $departmentIds],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}

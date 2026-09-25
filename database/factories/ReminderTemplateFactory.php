<?php

namespace Database\Factories;

use App\Models\ReminderTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReminderTemplate>
 */
class ReminderTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Training comeback reminder',
                'Pre-test completion nudge',
                'Post-test unlock reminder',
            ]),
            'subject' => 'A quick reminder from Guesvia',
            'body' => "Hello {{name}},\n\n"
                .'Your English training at {{hotel}} is waiting for you. '
                ."You have completed {{progress}}% so far and {{days_remaining}} days remain.\n\n"
                .'Continue here: {{login_url}}',
            'audience_label' => 'Inactive employees',
            'trigger_label' => '5 days without activity',
            'is_active' => true,
        ];
    }

    /** A template the send dialog and the runner must not use. */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Reminder;
use App\Models\ReminderReply;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReminderReply>
 */
class ReminderReplyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reminder_id' => Reminder::factory()->inApp()->state(['allow_replies' => true]),
            'user_id' => fn (array $attributes) => Reminder::query()->whereKey($attributes['reminder_id'])->value('user_id'),
            'body' => 'The lessons are clear. More listening practice, please.',
            'rating' => null,
            'read_at' => null,
        ];
    }

    public function rated(int $rating = 4): static
    {
        return $this->state(fn (array $attributes) => ['rating' => $rating]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => ['read_at' => now()]);
    }
}

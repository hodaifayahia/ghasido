<?php

namespace Database\Factories;

use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
use App\Models\Reminder;
use App\Models\ReminderTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'template_id' => ReminderTemplate::factory(),
            'automation_rule_id' => null,
            'channel' => ReminderChannel::Email,
            'subject' => 'A quick reminder from Guesvia',
            'body' => 'Your English training is waiting for you.',
            'sent_by' => null,
            'scheduled_for' => null,
            'sent_at' => now(),
            'status' => ReminderStatus::Sent,
            'blocked_reason' => null,
            'read_at' => null,
            'provider_message_id' => null,
        ];
    }

    /** Written, job dispatched, mail not yet sent. */
    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReminderStatus::Queued,
            'sent_at' => null,
        ]);
    }

    /** Never sent, because consent or an address was missing (REM-05). */
    public function blocked(string $reason = Reminder::BLOCKED_NO_CONSENT): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReminderStatus::Blocked,
            'blocked_reason' => $reason,
            'sent_at' => null,
        ]);
    }

    public function failed(string $reason = 'Connection refused'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReminderStatus::Failed,
            'blocked_reason' => $reason,
            'sent_at' => null,
        ]);
    }

    /** An in-app message: sent the moment it exists, unread until opened. */
    public function inApp(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => ReminderChannel::InApp,
            'status' => ReminderStatus::Sent,
            'sent_at' => now(),
            'read_at' => null,
        ]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now(),
        ]);
    }
}

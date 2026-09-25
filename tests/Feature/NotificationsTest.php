<?php

namespace Tests\Feature;

use App\Enums\ReminderChannel;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_shared_notification_menu_includes_only_own_in_app_reminders_within_24_hours()
    {
        $recipient = User::factory()->superAdmin()->create();
        $otherUser = User::factory()->create();

        $unread = Reminder::factory()->for($recipient)->inApp()->create([
            'subject' => 'Unread notice',
            'sent_at' => now()->subHours(23),
        ]);
        $read = Reminder::factory()->for($recipient)->inApp()->read()->create([
            'subject' => 'Read notice',
            'sent_at' => now()->subHour(),
        ]);
        $email = Reminder::factory()->for($recipient)->create([
            'subject' => 'Email reminder',
            'sent_at' => now()->subMinutes(30),
        ]);
        $expired = Reminder::factory()->for($recipient)->inApp()->create([
            'sent_at' => now()->subHours(24),
        ]);
        Reminder::factory()->for($otherUser)->inApp()->create();

        $this->actingAs($recipient)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.unread', 2)
                ->where('notifications.items', fn (Collection $items): bool => $items->pluck('id')->all() === [$email->id, $read->id, $unread->id])
                ->where('notifications.items.0.channel', ReminderChannel::Email->value)
                ->where('notifications.items.1.expiresAt', $read->sent_at?->copy()
                    ->addHours(Reminder::IN_APP_EXPIRY_HOURS)
                    ->toIso8601String())
            );

        // Expiry only removes a reminder from notification surfaces, not the
        // historical reminder log (DATA-10).
        $this->assertDatabaseHas('reminders', ['id' => $expired->id]);
    }

    public function test_messages_page_uses_the_same_24_hour_visibility_window()
    {
        $recipient = User::factory()->superAdmin()->create();

        Reminder::factory()->for($recipient)->create([
            'sent_at' => now()->subMinutes(20),
        ]);
        Reminder::factory()->for($recipient)->inApp()->create([
            'sent_at' => now()->subHours(25),
        ]);

        $this->actingAs($recipient)
            ->get(route('learn.messages'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('employee/Messages')
                ->has('messages', 1)
                ->where('messages.0.channel', ReminderChannel::Email->value)
                ->where('unread', 1)
            );
    }

    public function test_notification_read_route_marks_only_the_recipients_in_app_reminders_read()
    {
        $recipient = User::factory()->superAdmin()->create();
        $otherUser = User::factory()->superAdmin()->create();
        $notice = Reminder::factory()->for($recipient)->inApp()->create();
        $expired = Reminder::factory()->for($recipient)->inApp()->create([
            'sent_at' => now()->subHours(Reminder::IN_APP_EXPIRY_HOURS),
        ]);
        $email = Reminder::factory()->for($recipient)->create();
        $blocked = Reminder::factory()->for($recipient)->blocked()->create();

        $this->actingAs($recipient)
            ->from(route('dashboard'))
            ->post(route('notifications.read', ['reminder' => $notice]))
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($notice->fresh()?->read_at);

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.unread', 1)
            );

        $this->actingAs($recipient)
            ->post(route('notifications.read', ['reminder' => $email]))
            ->assertRedirect();

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.unread', 0)
            );

        $this->actingAs($otherUser)
            ->post(route('notifications.read', ['reminder' => $notice]))
            ->assertForbidden();

        $this->actingAs($recipient)
            ->post(route('notifications.read', ['reminder' => $blocked]))
            ->assertNotFound();

        $this->actingAs($recipient)
            ->post(route('notifications.read', ['reminder' => $expired]))
            ->assertNotFound();

        $this->assertNotNull($email->fresh()?->read_at);
        $this->assertNull($blocked->fresh()?->read_at);
        $this->assertNull($expired->fresh()?->read_at);
    }
}

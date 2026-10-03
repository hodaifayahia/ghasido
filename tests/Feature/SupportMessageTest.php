<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Client request 2026-10-02: an employee or manager with a problem, or who
 * wants to extend, messages the GHASIDO team from Help; it rings the Super
 * Admin's bell.
 */
class SupportMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manager_messages_the_team_and_the_super_admin_sees_it()
    {
        $this->withoutVite();
        $hotel = Hotel::factory()->active()->create(['name' => 'Blue Coast']);
        $manager = User::factory()->manager()->create(['hotel_id' => $hotel->id, 'name' => 'Nassim']);
        $owner = User::factory()->superAdmin()->create();

        $this->actingAs($manager)->get(route('help'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('support'));

        $this->actingAs($manager)
            ->post(route('help.message'), ['topic' => 'extension', 'message' => 'We would like ten more seats next month.'])
            ->assertSessionHasNoErrors();

        $message = ContactMessage::query()->sole();
        $this->assertSame($manager->id, $message->user_id);
        $this->assertSame('extension', $message->topic);
        $this->assertSame('Blue Coast', $message->organisation);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.unread', 1)
                ->where('notifications.items.0.subject', 'Support request from Nassim · Blue Coast'));
    }

    public function test_the_message_and_topic_are_checked()
    {
        $user = User::factory()->employee()->create();

        $this->actingAs($user)
            ->post(route('help.message'), ['topic' => 'spam', 'message' => 'short'])
            ->assertSessionHasErrors(['topic', 'message']);

        $this->assertDatabaseCount('contact_messages', 0);
    }
}

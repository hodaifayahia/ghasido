<?php

namespace Tests\Feature;

use App\Mail\CustomerMessageMail;
use App\Models\ContactMessage;
use App\Models\Hotel;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Client request 2026-10-03: the Super Admin reads a message on the site,
 * answers it there, and a notification click opens its page.
 */
class InboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_super_admin_reads_and_answers_a_support_message()
    {
        $this->withoutVite();
        Mail::fake();
        $hotel = Hotel::factory()->active()->create(['name' => 'Blue Coast']);
        $manager = User::factory()->manager()->create(['hotel_id' => $hotel->id, 'name' => 'Nassim', 'email' => 'nassim@example.test']);
        $owner = User::factory()->superAdmin()->create();

        $this->actingAs($manager)
            ->post(route('help.message'), ['topic' => 'problem', 'message' => 'The audio does not play on my phone.'])
            ->assertSessionHasNoErrors();
        $message = ContactMessage::query()->sole();

        // The bell item opens the message in the inbox.
        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.items.0.url', '/inbox?message='.$message->id));

        $this->actingAs($owner)
            ->get(route('inbox', ['message' => $message->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Inbox')
                ->where('selectedId', $message->id)
                ->where('messages.0.message', 'The audio does not play on my phone.')
                ->where('messages.0.topic', 'problem'));
        $this->assertNotNull($message->fresh()?->read_at);

        $this->actingAs($owner)
            ->post(route('inbox.reply', $message), ['body' => 'Please update the browser and try again.'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('contact_replies', ['contact_message_id' => $message->id, 'sent_by' => $owner->id]);
        Mail::assertQueued(CustomerMessageMail::class, fn (CustomerMessageMail $mail) => $mail->hasTo('nassim@example.test'));

        $reminder = Reminder::query()->where('user_id', $manager->id)->sole();
        $this->assertSame('Please update the browser and try again.', $reminder->body);

        // The writer sees the answer on Help, and the bell opens it.
        $this->actingAs($manager)
            ->get(route('help'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversations.0.replies.0.body', 'Please update the browser and try again.')
                ->where('notifications.items.0.url', '/help#help-contact'));
    }

    public function test_only_the_super_admin_opens_the_inbox()
    {
        $hotel = Hotel::factory()->active()->create();
        $manager = User::factory()->manager()->create(['hotel_id' => $hotel->id]);
        $message = ContactMessage::query()->create(['name' => 'A', 'email' => 'a@example.test', 'message' => 'Hello there team']);

        $this->actingAs($manager)->get(route('inbox'))->assertForbidden();
        $this->actingAs($manager)->post(route('inbox.reply', $message), ['body' => 'x'])->assertForbidden();
    }
}

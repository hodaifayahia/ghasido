<?php

namespace Tests\Feature\Admin\Messages;

use App\Enums\AccountStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Reminder;
use App\Models\ReminderReply;
use App\Models\ReminderTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Replies to a message from Messages & Reminders (client request
 * 2026-11-07): the admin sends a message that allows replies, the learner
 * answers it from Notifications, the admin reads the answer. A learner may
 * answer only their own message, and only one that allows replies; a
 * manager reads only their own hotel's replies (REM-07, ROLE-02, SEC-01).
 */
class ReminderRepliesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Hotel $hotel;

    private Department $department;

    private ReminderTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inertia.testing.ensure_pages_exist', false);
        $this->withoutVite();
        Queue::fake();
        Mail::fake();

        $this->admin = User::factory()->superAdmin()->create();
        $this->hotel = Hotel::factory()->create();
        $this->department = Department::factory()->create();
        $this->template = ReminderTemplate::factory()->create([
            'name' => 'Your opinion',
            'subject' => 'What do you think of the lessons?',
            'body' => 'Tell us what you think and what we could do better.',
        ]);
    }

    public function test_an_admin_sends_a_message_that_allows_replies_with_a_rating()
    {
        $learner = $this->learner();

        $this->actingAs($this->admin)
            ->from(route('messages-reminders'))
            ->post(route('messages-reminders.send'), [
                'template_id' => $this->template->id,
                'channel' => 'in_app',
                'recipients' => [$learner->id],
                'allow_replies' => '1',
                'ask_rating' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $reminder = Reminder::query()->where('user_id', $learner->id)->sole();
        $this->assertTrue($reminder->allow_replies);
        $this->assertTrue($reminder->ask_rating);
        $this->assertNotNull($reminder->batch);

        $this->actingAs($learner)
            ->get(route('learn.messages'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('employee/Messages')
                ->where('messages.0.id', $reminder->id)
                ->where('messages.0.allowReplies', true)
                ->where('messages.0.askRating', true)
                ->where('messages.0.reply', null)
            );
    }

    public function test_a_plain_send_does_not_allow_replies_and_a_rating_needs_replies()
    {
        $learner = $this->learner();

        $this->actingAs($this->admin)
            ->post(route('messages-reminders.send'), [
                'template_id' => $this->template->id,
                'channel' => 'in_app',
                'recipients' => [$learner->id],
                'ask_rating' => '1',
            ])
            ->assertSessionHasNoErrors();

        $reminder = Reminder::query()->where('user_id', $learner->id)->sole();
        $this->assertFalse($reminder->allow_replies);
        $this->assertFalse($reminder->ask_rating);
    }

    public function test_the_learner_replies_and_the_admin_reads_the_reply()
    {
        $learner = $this->learner(['name' => 'Samira Haddad']);
        $reminder = $this->message($learner, askRating: true);
        $text = "  The lessons are clear.\nMore listening, please.  ";

        $this->actingAs($learner)
            ->from(route('learn.messages'))
            ->put(route('learn.messages.reply', ['reminder' => $reminder]), [
                'body' => $text,
                'rating' => 4,
            ])
            ->assertRedirect(route('learn.messages'))
            ->assertSessionHasNoErrors();

        $reply = ReminderReply::query()->sole();
        // Stored exactly as written.
        $this->assertSame($text, $reply->body);
        $this->assertSame(4, $reply->rating);
        $this->assertSame($learner->id, $reply->user_id);
        $this->assertNull($reply->read_at);
        // Answering a message is reading it.
        $this->assertNotNull($reminder->fresh()?->read_at);

        // The learner sees their answer and may change it: still one row.
        $this->actingAs($learner)
            ->put(route('learn.messages.reply', ['reminder' => $reminder]), ['body' => 'Changed my mind.', 'rating' => 5])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, ReminderReply::query()->count());
        $this->assertSame('Changed my mind.', $reply->fresh()?->body);

        $this->actingAs($learner)
            ->get(route('learn.messages'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('messages.0.reply.body', 'Changed my mind.')
                ->where('messages.0.reply.rating', 5)
            );

        // The admin: an unread badge, the figures on the log row, and the
        // reply itself in the Replies panel.
        $this->actingAs($this->admin)
            ->get(route('messages-reminders'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('repliesUnread', 1)
                ->where('logs.0.allowReplies', true)
                ->where('logs.0.replied', true)
                ->where('logs.0.replyCount', 1)
                ->where('logs.0.recipientCount', 1)
                ->where('logs.0.deletable', false)
                ->missing('replyMessages')
                ->where('notifications.unread', 1)
            );

        $this->actingAs($this->admin)
            ->get(route('messages-reminders', ['replies' => $reminder->batch]), $this->partial('replyMessages'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('replyMessages', 1)
                ->where('replyMessages.0.batch', $reminder->batch)
                ->where('replyMessages.0.title', 'Your opinion')
                ->where('replyMessages.0.replyCount', 1)
                ->where('replyMessages.0.unread', 1)
                ->where('replyMessages.0.averageRating', 5)
                ->where('replyMessages.0.replies.0.name', 'Samira Haddad')
                ->where('replyMessages.0.replies.0.body', 'Changed my mind.')
                ->where('replyMessages.0.replies.0.rating', 5)
            );

        $this->actingAs($this->admin)
            ->post(route('messages-reminders.replies.read', ['batch' => $reminder->batch]))
            ->assertRedirect();
        $this->assertNotNull($reply->fresh()?->read_at);
    }

    public function test_a_learner_may_rate_without_text_only_when_asked()
    {
        $learner = $this->learner();
        $rated = $this->message($learner, askRating: true);
        $plain = $this->message($learner);

        $this->actingAs($learner)
            ->put(route('learn.messages.reply', ['reminder' => $rated]), ['body' => '', 'rating' => 3])
            ->assertSessionHasNoErrors();
        $this->assertNull(ReminderReply::query()->where('reminder_id', $rated->id)->value('body'));

        $this->actingAs($learner)
            ->put(route('learn.messages.reply', ['reminder' => $plain]), ['body' => '   '])
            ->assertSessionHasErrors('body');
        $this->actingAs($learner)
            ->put(route('learn.messages.reply', ['reminder' => $plain]), ['body' => 'Fine.', 'rating' => 3])
            ->assertSessionHasErrors('rating');
        $this->actingAs($learner)
            ->put(route('learn.messages.reply', ['reminder' => $plain]), ['body' => str_repeat('a', ReminderReply::MAX_BODY + 1)])
            ->assertSessionHasErrors('body');
        $this->actingAs($learner)
            ->put(route('learn.messages.reply', ['reminder' => $rated]), ['rating' => 6])
            ->assertSessionHasErrors('rating');
    }

    public function test_a_learner_cannot_reply_to_a_message_not_sent_to_them()
    {
        $learner = $this->learner();
        $colleague = $this->learner();
        $theirs = $this->message($colleague);

        $this->actingAs($learner)
            ->put(route('learn.messages.reply', ['reminder' => $theirs]), ['body' => 'Not mine to answer.'])
            ->assertForbidden();

        $this->assertDatabaseCount('reminder_replies', 0);
    }

    public function test_a_learner_cannot_reply_to_a_message_that_does_not_allow_replies()
    {
        $learner = $this->learner();
        $notice = Reminder::factory()->for($learner)->inApp()->create(['allow_replies' => false]);

        $this->actingAs($learner)
            ->put(route('learn.messages.reply', ['reminder' => $notice]), ['body' => 'Hello?'])
            ->assertForbidden();

        $this->assertDatabaseCount('reminder_replies', 0);
    }

    public function test_the_super_admin_cannot_reply_on_a_learners_behalf()
    {
        $learner = $this->learner();
        $reminder = $this->message($learner);

        $this->actingAs($this->admin)
            ->put(route('learn.messages.reply', ['reminder' => $reminder]), ['body' => 'Answering for them.'])
            ->assertForbidden();
    }

    public function test_a_message_that_allows_replies_stays_answerable_for_a_week()
    {
        $learner = $this->learner();
        $recent = $this->message($learner, sentAt: now()->subDays(3));
        $old = $this->message($learner, sentAt: now()->subDays(Reminder::REPLY_WINDOW_DAYS + 1));

        $this->actingAs($learner)
            ->get(route('learn.messages'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('messages', 1)
                ->where('messages.0.id', $recent->id)
            );

        $this->actingAs($learner)
            ->put(route('learn.messages.reply', ['reminder' => $old]), ['body' => 'Too late?'])
            ->assertNotFound();
    }

    public function test_a_manager_sees_only_their_own_hotels_replies()
    {
        $other = Hotel::factory()->create();
        $manager = User::factory()->manager()->create([
            'hotel_id' => $this->hotel->id,
            'status' => AccountStatus::Active,
        ]);
        $own = $this->learner(['name' => 'Own Learner']);
        $foreign = $this->learner(['hotel_id' => $other->id, 'name' => 'Foreign Learner']);

        // One message to both hotels, as the Super Admin may send.
        $this->actingAs($this->admin)
            ->post(route('messages-reminders.send'), [
                'template_id' => $this->template->id,
                'channel' => 'in_app',
                'recipients' => [$own->id, $foreign->id],
                'allow_replies' => '1',
            ])
            ->assertSessionHasNoErrors();

        $ownReminder = Reminder::query()->where('user_id', $own->id)->sole();
        $foreignReminder = Reminder::query()->where('user_id', $foreign->id)->sole();
        $batch = (string) $ownReminder->batch;

        foreach ([[$own, $ownReminder], [$foreign, $foreignReminder]] as [$learner, $reminder]) {
            $this->actingAs($learner)
                ->put(route('learn.messages.reply', ['reminder' => $reminder]), ['body' => 'From '.$learner->name])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($manager)
            ->get(route('messages-reminders', ['replies' => $batch]), $this->partial('replyMessages'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('replyMessages', 1)
                ->where('replyMessages.0.recipients', 1)
                ->where('replyMessages.0.replyCount', 1)
                ->has('replyMessages.0.replies', 1)
                ->where('replyMessages.0.replies.0.name', 'Own Learner')
            );

        $this->actingAs($manager)
            ->get(route('messages-reminders'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('repliesUnread', 1)
                ->where('notifications.unread', 1)
            );

        // Marking the message read touches only the manager's own hotel.
        $this->actingAs($manager)
            ->post(route('messages-reminders.replies.read', ['batch' => $batch]))
            ->assertRedirect();

        $this->assertNotNull(ReminderReply::query()->where('user_id', $own->id)->value('read_at'));
        $this->assertNull(ReminderReply::query()->where('user_id', $foreign->id)->value('read_at'));

        $this->actingAs($this->admin)
            ->get(route('messages-reminders'))
            ->assertInertia(fn (Assert $page) => $page->where('repliesUnread', 1));
    }

    public function test_a_learner_cannot_open_the_admin_replies()
    {
        $learner = $this->learner();
        $reminder = $this->message($learner);

        $this->actingAs($learner)
            ->post(route('messages-reminders.replies.read', ['batch' => (string) $reminder->batch]))
            ->assertForbidden();
    }

    public function test_a_replied_log_row_is_kept_when_deletion_is_asked()
    {
        $learner = $this->learner();
        $reminder = $this->message($learner);
        ReminderReply::factory()->create(['reminder_id' => $reminder->id, 'user_id' => $learner->id]);

        $this->actingAs($this->admin)
            ->delete(route('messages-reminders.log.destroy', ['reminder' => $reminder]))
            ->assertRedirect();

        $this->assertDatabaseHas('reminders', ['id' => $reminder->id]);
        $this->assertDatabaseCount('reminder_replies', 1);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function learner(array $attributes = []): User
    {
        return User::factory()
            ->employee()
            ->withUsername()
            ->firstLoginDone()
            ->forHotel($this->hotel, $this->department)
            ->create($attributes);
    }

    private function message(User $learner, bool $askRating = false, mixed $sentAt = null): Reminder
    {
        return Reminder::factory()->for($learner)->inApp()->create([
            'template_id' => $this->template->id,
            'batch' => fake()->uuid(),
            'allow_replies' => true,
            'ask_rating' => $askRating,
            'sent_at' => $sentAt ?? now(),
        ]);
    }

    /**
     * Headers of an Inertia partial reload asking for one optional prop.
     *
     * @return array<string, string>
     */
    private function partial(string $prop): array
    {
        $version = (string) app(HandleInertiaRequests::class)->version(request());

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'admin/MessagesReminders',
            'X-Inertia-Partial-Data' => $prop,
        ];
    }
}

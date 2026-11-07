<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Http\Requests\Learn\ReplyToReminderRequest;
use App\Models\Reminder;
use App\Models\User;
use App\Services\Reminders\ReminderReplyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Delivered reminders shown in the learner's inbox (REM-01, REM-04, REM-08).
 *
 * The employee's own in-app reminders, newest first. Opening one stamps
 * `read_at`, which is what the topbar bell counts down. A message sent with
 * replies allowed can be answered from here, with a 1-5 rating when the
 * sender asked for one (client request 2026-11-07).
 */
class MessagesController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $messages = $user->reminders()
            ->visibleInNotificationCenter()
            ->with('reply')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('employee/Messages', [
            'messages' => $messages->map(fn (Reminder $reminder): array => [
                'id' => $reminder->id,
                'channel' => $reminder->channel->value,
                'subject' => $reminder->subject,
                'body' => $reminder->body,
                'sentAt' => $reminder->noticedAt()?->toIso8601String(),
                'expiresAt' => $reminder->visibleUntil()?->toIso8601String(),
                'readAt' => $reminder->read_at?->toIso8601String(),
                'read' => $reminder->read_at !== null,
                'readUrl' => route('learn.messages.read', ['reminder' => $reminder]),
                'allowReplies' => $reminder->allow_replies,
                'askRating' => $reminder->allow_replies && $reminder->ask_rating,
                'reply' => $reminder->reply === null ? null : [
                    'body' => $reminder->reply->body,
                    'rating' => $reminder->reply->rating,
                    'repliedAt' => ($reminder->reply->updated_at ?? $reminder->reply->created_at)?->toIso8601String(),
                ],
            ])->values()->all(),
            'unread' => $messages->whereNull('read_at')->count(),
        ]);
    }

    public function read(Request $request, Reminder $reminder): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($reminder->user_id === $user->id, 403);
        abort_unless($reminder->isVisibleInNotificationCenter(), 404);

        $reminder->markRead();

        return back();
    }

    /**
     * Answer a message that allowed replies, or change the answer. The
     * request refuses (403) a message that is not this learner's or does
     * not take replies; an expired one is gone from the inbox (404).
     */
    public function reply(ReplyToReminderRequest $request, Reminder $reminder, ReminderReplyService $replies): RedirectResponse
    {
        abort_unless($reminder->isVisibleInNotificationCenter(), 404);

        /** @var User $user */
        $user = $request->user('web');

        $saved = $replies->save($reminder, $user, $request->body(), $request->rating());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $saved['created']
                ? __('Thank you. Your reply was sent.')
                : __('Your reply was updated.'),
        ]);

        return back();
    }
}

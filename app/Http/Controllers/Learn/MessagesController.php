<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Delivered reminders shown in the learner's inbox (REM-01, REM-04, REM-08).
 *
 * The employee's own in-app reminders, newest first. Opening one stamps
 * `read_at`, which is what the topbar bell counts down.
 */
class MessagesController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $messages = $user->reminders()
            ->visibleInNotificationCenter()
            ->orderByDesc('id')
            ->get();

        return Inertia::render('employee/Messages', [
            'messages' => $messages->map(fn (Reminder $reminder): array => [
                'id' => $reminder->id,
                'channel' => $reminder->channel->value,
                'subject' => $reminder->subject,
                'body' => $reminder->body,
                'sentAt' => $reminder->noticedAt()?->toIso8601String(),
                'expiresAt' => $reminder->noticedAt()?->copy()
                    ->addHours(Reminder::IN_APP_EXPIRY_HOURS)
                    ->toIso8601String(),
                'readAt' => $reminder->read_at?->toIso8601String(),
                'read' => $reminder->read_at !== null,
                'readUrl' => route('learn.messages.read', ['reminder' => $reminder]),
            ])->values()->all(),
            'unread' => $messages->whereNull('read_at')->count(),
        ]);
    }

    public function read(Request $request, Reminder $reminder): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($reminder->user_id === $user->id, 403);
        abort_unless(
            Reminder::query()->visibleInNotificationCenter()->whereKey($reminder->id)->exists(),
            404,
        );

        $reminder->markRead();

        return back();
    }
}

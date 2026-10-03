<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
use App\Http\Controllers\Controller;
use App\Mail\CustomerMessageMail;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\ContactReply;
use App\Models\Reminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * The Super Admin's inbox (client request 2026-10-03: "messages only ring
 * the bell; I cannot read them on the site to answer"). Every message from
 * the public Contact form and from Help, read in full and answered here.
 * An answer reaches the writer's bell (when they have an account) and their
 * email.
 */
final class InboxController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::LandingManage->value);

        $selectedId = $request->integer('message');

        $messages = ContactMessage::query()
            ->with(['replies.sender'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $selected = $selectedId > 0 ? $messages->firstWhere('id', $selectedId) : null;

        // Opening a message is reading it, so the bell counts it down.
        if ($selected !== null && $selected->read_at === null) {
            $selected->forceFill(['read_at' => now()])->save();
        }

        return Inertia::render('admin/Inbox', [
            'messages' => $messages->map(fn (ContactMessage $message): array => [
                'id' => $message->id,
                'name' => $message->name,
                'email' => $message->email,
                'phone' => $message->phone,
                'organisation' => $message->organisation,
                'topic' => $message->topic,
                'fromAccount' => $message->user_id !== null,
                'message' => $message->topic !== null
                    ? (string) Str::of($message->message)->after('['.$message->topic.'] ')
                    : $message->message,
                'sentAt' => $message->created_at?->toIso8601String() ?? '',
                'read' => $message->read_at !== null,
                'replies' => $message->replies->map(fn (ContactReply $reply): array => [
                    'id' => $reply->id,
                    'body' => $reply->body,
                    'sender' => $reply->sender->name ?? __('GHASIDO team'),
                    'sentAt' => $reply->created_at?->toIso8601String() ?? '',
                ])->values()->all(),
            ])->values()->all(),
            'selectedId' => $selected?->id,
            'unread' => $messages->whereNull('read_at')->count(),
        ]);
    }

    public function reply(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        Gate::authorize(Permission::LandingManage->value);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $body = (string) $data['body'];
        $sender = $request->user('web');

        $contactMessage->replies()->create([
            'body' => $body,
            'sent_by' => $sender?->id,
        ]);
        $contactMessage->forceFill(['read_at' => $contactMessage->read_at ?? now()])->save();

        $subject = __('Reply from the GHASIDO team');
        $writer = $contactMessage->user()->first();

        // In the writer's bell at once; a click opens Help, where the whole
        // conversation is.
        if ($writer !== null) {
            Reminder::query()->create([
                'user_id' => $writer->id,
                'channel' => ReminderChannel::InApp,
                'subject' => $subject,
                'body' => $body,
                'sent_by' => $sender?->id,
                'status' => ReminderStatus::Sent,
                'sent_at' => now(),
                'link' => route('help', [], false).'#help-contact',
            ]);
        }

        $email = trim($contactMessage->email);
        $mailed = false;

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($email, $contactMessage->name)
                    ->locale($writer->locale ?? 'en')
                    ->queue(new CustomerMessageMail($contactMessage->name, $subject, $body));
                $mailed = true;
            } catch (Throwable $e) {
                // Stored and in the bell; a mail problem must not lose it.
                Log::warning('Inbox reply mail failed', ['id' => $contactMessage->id, 'error' => $e->getMessage()]);
            }
        }

        AuditLog::record($contactMessage, 'contact_message.replied', ['emailed' => $mailed]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $mailed
            ? __('Reply sent to :email.', ['email' => $email])
            : __('Reply saved. It reached their notifications.')]);

        return back();
    }
}

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
use App\Models\LevelChangeRequest;
use App\Models\Reminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
            'tab' => $request->query('tab') === 'levels' ? 'levels' : 'messages',
            'levelRequests' => $this->levelRequests($request),
            'pendingLevelRequests' => LevelChangeRequest::query()->where('status', 'pending')->count(),
        ]);
    }

    /**
     * Level change requests, waiting ones first (client request
     * 2026-10-04). Opening the tab marks them seen for the bell.
     *
     * @return array<int, array<string, mixed>>
     */
    private function levelRequests(Request $request): array
    {
        if ($request->query('tab') === 'levels') {
            LevelChangeRequest::query()->whereNull('read_at')->update(['read_at' => now()]);
        }

        return LevelChangeRequest::query()
            ->with(['user', 'hotel'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (LevelChangeRequest $levelRequest): array => [
                'id' => $levelRequest->id,
                'name' => $levelRequest->user->name ?? __('Removed account'),
                'username' => $levelRequest->user?->username,
                'hotel' => $levelRequest->hotel?->name,
                'from' => $levelRequest->from_level?->label(),
                'to' => $levelRequest->to_level->label(),
                'reason' => $levelRequest->reason,
                'status' => $levelRequest->status,
                'note' => $levelRequest->decision_note,
                'sentAt' => $levelRequest->created_at?->toIso8601String() ?? '',
                'decidedAt' => $levelRequest->decided_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * Approve or refuse a level change request (client request 2026-10-04).
     * Approved: the learner's level changes now and their new path starts.
     * Either way the learner hears it in their notifications.
     */
    public function decideLevel(Request $request, LevelChangeRequest $levelRequest): RedirectResponse
    {
        Gate::authorize(Permission::LandingManage->value);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        abort_unless($levelRequest->status === 'pending', 409, __('This request was already answered.'));

        $learner = $levelRequest->user;
        abort_if($learner === null, 404);

        $approve = $data['decision'] === 'approve';
        $note = isset($data['note']) && trim((string) $data['note']) !== '' ? trim((string) $data['note']) : null;
        $actor = $request->user('web');

        $levelRequest->forceFill([
            'status' => $approve ? 'approved' : 'rejected',
            'decided_by' => $actor?->id,
            'decided_at' => now(),
            'decision_note' => $note,
            'read_at' => $levelRequest->read_at ?? now(),
        ])->save();

        if ($approve) {
            $learner->forceFill([
                'english_level' => $levelRequest->to_level,
                'english_level_assessed_at' => now(),
                'level_suggestion' => null,
            ])->save();
        }

        $level = $levelRequest->to_level->label();

        Reminder::query()->create([
            'user_id' => $learner->id,
            'channel' => ReminderChannel::InApp,
            'subject' => $approve
                ? __('Your level change was approved', [], $learner->locale ?? 'en')
                : __('Your level change was not approved', [], $learner->locale ?? 'en'),
            'body' => trim(($approve
                ? __('Your level is now :level. Your lessons and tests follow it.', ['level' => $level], $learner->locale ?? 'en')
                : __('You stay at your current level. Finish its Post-test to choose another one.', [], $learner->locale ?? 'en'))
                .($note !== null ? "\n\n".$note : '')),
            'sent_by' => $actor?->id,
            'status' => ReminderStatus::Sent,
            'sent_at' => now(),
            'link' => route('learn.progress', [], false),
        ]);

        AuditLog::record($levelRequest, $approve ? 'learner.level.change_approved' : 'learner.level.change_rejected', [
            'user_id' => $learner->id,
            'to' => $levelRequest->to_level->value,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $approve
            ? __(':name is now at :level.', ['name' => $learner->name, 'level' => $level])
            : __('The request from :name was refused.', ['name' => $learner->name])]);

        return back();
    }

    /** The bell marks one request seen. */
    public function seenLevel(LevelChangeRequest $levelRequest): RedirectResponse
    {
        Gate::authorize(Permission::LandingManage->value);

        $levelRequest->forceFill(['read_at' => $levelRequest->read_at ?? now()])->save();

        return back();
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

<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\Landing\LandingPageContentStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Throwable;

/**
 * "Message the GHASIDO team" on Help (client request 2026-10-02): an
 * employee, manager or hotel admin reports a problem or asks to extend.
 * Stored with the contact messages, so it rings the Super Admin's bell, and
 * forwarded to the support email when one is set (like the public form).
 */
final class SupportMessageController extends Controller
{
    public function store(Request $request, LandingPageContentStore $content): RedirectResponse
    {
        $data = $request->validate([
            'topic' => ['required', 'string', Rule::in(ContactMessage::TOPICS)],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        /** @var User $user */
        $user = $request->user('web');

        $message = ContactMessage::query()->create([
            'user_id' => $user->id,
            'topic' => $data['topic'],
            'name' => $user->name,
            // The username when no email is on file, so the team knows who.
            'email' => (string) ($user->email ?? $user->username ?? ''),
            'phone' => $user->phone,
            'organisation' => $user->hotel()->withoutGlobalScopes()->value('name'),
            'message' => '['.$data['topic'].'] '.$data['message'],
            'ip' => $request->ip(),
        ]);

        $to = trim((string) data_get($content->current(), 'support.email', ''));

        if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($to)->queue(new ContactMessageMail($message));
            } catch (Throwable $e) {
                // Stored and in the bell; a mail problem must not lose it.
                Log::warning('Support message mail failed', ['id' => $message->id, 'error' => $e->getMessage()]);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your message was sent to the GHASIDO team. We will get back to you soon.')]);

        return back();
    }
}

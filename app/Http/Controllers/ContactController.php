<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use App\Services\Landing\LandingPageContentStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * The public Contact Us page (client decision 2026-09-26): the phone and
 * email the Super Admin set in Settings → Landing page, and a form. Every
 * message is stored first, then forwarded to the support email when one is
 * set, so nothing is lost while outgoing mail is not configured.
 */
final class ContactController extends Controller
{
    public function show(LandingPageContentStore $content): Response
    {
        return Inertia::render('Contact', [
            'content' => $content->current(),
        ]);
    }

    public function store(Request $request, LandingPageContentStore $content): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^\+?[0-9().\s-]{6,}$/'],
            'organisation' => ['nullable', 'string', 'max:160'],
            'employees' => ['nullable', 'string', 'max:40'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            // A hidden field real visitors never fill in.
            'website' => ['nullable', 'max:0'],
        ], [
            'phone.regex' => __('Enter a valid phone number.'),
        ]);

        unset($data['website']);

        $message = ContactMessage::query()->create([
            ...$data,
            'ip' => $request->ip(),
        ]);

        $to = trim((string) data_get($content->current(), 'support.email', ''));

        if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($to)->queue(new ContactMessageMail($message));
            } catch (Throwable $e) {
                // The message is stored; a mail problem must not lose it.
                Log::warning('Contact message mail failed', ['id' => $message->id, 'error' => $e->getMessage()]);
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => (string) data_get($content->current(), 'contact.success_message', __('Thank you. We will get back to you soon.')),
        ]);

        return back();
    }
}

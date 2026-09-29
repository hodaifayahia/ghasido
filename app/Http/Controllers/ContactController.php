<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use App\Services\Landing\LandingPageContentStore;
use App\Services\Mail\BusinessEmail;
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
 * message is stored first, then forwarded to the business email set in
 * Website Management when one is set, so nothing is lost while outgoing mail
 * is not configured. The Super Admin reads them all under Contact Requests.
 */
final class ContactController extends Controller
{
    public function show(LandingPageContentStore $content): Response
    {
        return Inertia::render('Contact', [
            'content' => $content->current(),
        ]);
    }

    public function store(Request $request, LandingPageContentStore $content, BusinessEmail $businessEmail): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:180'],
            // Required so the team can call back (user request 2026-09-26).
            'phone' => ['required', 'string', 'max:40', 'regex:/^\+?[0-9().\s-]{6,}$/'],
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

        $to = $businessEmail->address();

        if ($to !== null) {
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

<?php

namespace App\Mail;

use App\Models\Reminder;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * The email behind one Reminder row (REM-01).
 *
 * Subject and body are the text ReminderService rendered when the row was
 * written, so what the employee receives is exactly what the log says.
 * Sent synchronously from App\Jobs\SendReminderEmail, which is the queued
 * part; the mailable itself is not queued again.
 */
class ReminderMail extends BrandedMailable
{
    public function __construct(public Reminder $reminder) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->reminder->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.reminder',
            text: 'mail.text.reminder',
            with: [
                'subject' => $this->reminder->subject,
                'body' => $this->reminder->body,
                'trainingUrl' => route('dashboard'),
                'preferencesUrl' => route('profile.edit'),
            ],
        );
    }

    /**
     * List-Unsubscribe (Gmail, Outlook): the profile page, where the
     * employee withdraws reminder consent (PRIV-02, REM-05), and a mailto
     * to the reply address for clients that only offer that.
     */
    public function headers(): Headers
    {
        $mailto = (string) (config('mail.reply_to.address') ?: config('mail.from.address'));
        $targets = ['<'.route('profile.edit').'>'];

        if ($mailto !== '') {
            array_unshift($targets, '<mailto:'.$mailto.'?subject='.rawurlencode('Unsubscribe from reminders').'>');
        }

        return new Headers(text: ['List-Unsubscribe' => implode(', ', $targets)]);
    }
}

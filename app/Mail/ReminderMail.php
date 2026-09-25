<?php

namespace App\Mail;

use App\Models\Reminder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The email behind one Reminder row (REM-01).
 *
 * Subject and body are the text ReminderService rendered when the row was
 * written, so what the employee receives is exactly what the log says.
 * Sent synchronously from App\Jobs\SendReminderEmail, which is the queued
 * part; the mailable itself is not queued again.
 */
class ReminderMail extends Mailable
{
    use Queueable, SerializesModels;

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
            with: [
                'subject' => $this->reminder->subject,
                'body' => $this->reminder->body,
            ],
        );
    }
}

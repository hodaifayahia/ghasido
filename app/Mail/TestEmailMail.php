<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Settings → Email's "Send test email" (client request 2026-09-29). Sent
 * at once, never queued, so the Super Admin sees the mail server's answer.
 */
class TestEmailMail extends BrandedMailable
{
    public function __construct(
        public string $server,
        public string $fromLine,
        public string $sentAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('GHASIDO test email'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.test-email',
            text: 'mail.text.test-email',
            with: [
                'server' => $this->server,
                'from' => $this->fromLine,
                'sentAt' => $this->sentAt,
            ],
        );
    }
}

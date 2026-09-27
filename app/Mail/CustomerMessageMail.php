<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A message the Super Admin writes to a customer from the Payments page,
 * about their payment or subscription (client request 2026-09-27).
 */
class CustomerMessageMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $subjectLine,
        public string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.customer-message',
            with: [
                'name' => $this->recipientName,
                'subjectLine' => $this->subjectLine,
                'body' => $this->body,
            ],
        );
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when the Super Admin rejects a hotel or individual subscription
 * bought online, with the reason, so the customer can send a valid payment
 * (client request 2026-09-27).
 */
class AccountRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $accountName,
        public string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('We could not confirm your GHASIDO payment'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.account-rejected',
            with: [
                'name' => $this->recipientName,
                'accountName' => $this->accountName,
                'reason' => $this->reason,
            ],
        );
    }
}

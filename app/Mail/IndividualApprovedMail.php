<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when the Super Admin confirms an individual subscriber's payment and
 * activates their account (client request 2026-09-27).
 */
class IndividualApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $planName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Your GHASIDO subscription is active'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.individual-approved',
            with: [
                'name' => $this->user->name,
                'username' => $this->user->username,
                'planName' => $this->planName,
                'loginUrl' => route('login'),
            ],
        );
    }
}

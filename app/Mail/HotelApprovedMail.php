<?php

namespace App\Mail;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a Super Admin approves a hotel request and activates its first
 * manager account.
 */
class HotelApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Hotel $hotel,
        public User $manager,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your Guesvia hotel account is ready'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.hotel-approved',
            with: [
                'hotelName' => $this->hotel->name,
                'managerName' => $this->manager->name,
                'username' => $this->manager->username,
                'loginUrl' => route('login'),
            ],
        );
    }
}

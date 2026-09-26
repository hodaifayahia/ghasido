<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A Contact Us message, forwarded to the support email set in Settings →
 * Landing page. Replying answers the visitor directly.
 */
class ContactMessageMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contact) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->contact->email, $this->contact->name)],
            subject: __('New GHASIDO contact message from :name', ['name' => $this->contact->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.contact-message',
            with: ['contact' => $this->contact],
        );
    }
}

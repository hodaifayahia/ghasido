<?php

namespace App\Mail;

use App\Models\PaymentSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the Super Admins a customer has sent a payment to review (client
 * request 2026-09-27).
 */
class PaymentSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PaymentSubmission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('New payment to review'));
    }

    public function content(): Content
    {
        $submission = $this->submission;

        return new Content(
            view: 'mail.payment-submitted',
            with: [
                'customer' => $submission->hotel->name ?? $submission->payer_name,
                'planName' => $submission->plan_name,
                'amount' => number_format($submission->amount, $submission->currency === 'USD' ? 2 : 0).' '.$submission->currency,
                'method' => $submission->methodLabel(),
                'reference' => $submission->reference,
                'reviewUrl' => route('payments', ['payment' => $submission->id]),
            ],
        );
    }
}

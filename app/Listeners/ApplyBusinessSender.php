<?php

namespace App\Listeners;

use App\Services\Mail\BusinessEmail;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Every outgoing email carries the business identity set in Website
 * Management (user request 2026-09-26): the sender name, the business
 * email as From when the mail server can send for its domain, and the
 * business email as Reply-To when it cannot. Covers mailables,
 * notifications and the password reset email alike, at send time.
 *
 * A mailable that set its own From or Reply-To keeps it: a Contact Us
 * message still replies to the visitor (ContactMessageMail).
 */
final class ApplyBusinessSender
{
    public function __construct(private readonly BusinessEmail $business) {}

    public function handle(MessageSending $event): void
    {
        try {
            $from = $this->business->fromAddress();
            $name = $this->business->senderName();
            $replyTo = $this->business->address();
        } catch (Throwable $e) {
            // The business identity is presentation; the email still goes.
            Log::warning('Business sender not applied', ['error' => $e->getMessage()]);

            return;
        }

        if ($from === '') {
            return;
        }

        $message = $event->message;

        if ($this->usesServerSender($message)) {
            $message->from(new Address($from, $name));
        }

        if ($replyTo !== null && $message->getReplyTo() === [] && strcasecmp($replyTo, $from) !== 0) {
            $message->replyTo($replyTo);
        }
    }

    /** True when the From is the server default, not one a mailable chose. */
    private function usesServerSender(Email $message): bool
    {
        $current = $message->getFrom();

        return $current === []
            || (count($current) === 1 && strcasecmp($current[0]->getAddress(), BusinessEmail::serverAddress()) === 0);
    }
}

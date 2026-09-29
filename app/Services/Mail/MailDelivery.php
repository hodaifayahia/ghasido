<?php

namespace App\Services\Mail;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Send emails immediately" (Settings → Email, client request 2026-09-29).
 *
 * The shared host may have no cron running `queue:work`, so a queued email
 * would wait forever. With the switch on, a queued mailable is delivered
 * on the `sync` connection, inside the request that sent it; every other
 * queued job (AI, audio, exports) stays on the database queue.
 *
 * A mail server failure never breaks the request that sent the email (an
 * approval, a payment review): the error is logged and the mail is put on
 * the normal queue instead, so a worker can retry it if one runs.
 */
final class MailDelivery
{
    public function __construct(private readonly MailSettings $settings) {}

    public function immediate(): bool
    {
        return $this->settings->immediate();
    }

    /**
     * Run $push on the sync connection when immediate, falling back to the
     * default connection if the mail server fails.
     *
     * @template T
     *
     * @param  Closure(string|null): T  $push  receives the connection name
     * @return T
     */
    public function push(?string $connection, Closure $push, string $what): mixed
    {
        if (! $this->immediate()) {
            return $push($connection);
        }

        try {
            return $push('sync');
        } catch (Throwable $exception) {
            Log::warning('An email could not be sent immediately; it was queued instead.', [
                'mail' => $what,
                'error' => self::redact($exception->getMessage()),
            ]);

            return $push($connection);
        }
    }

    /**
     * A mail server error in plain words, for Settings → Email's test and
     * the logs.
     */
    public static function explain(Throwable $exception): string
    {
        $message = Str::lower($exception->getMessage());

        return match (true) {
            str_contains($message, '535') || str_contains($message, 'authenticat') || str_contains($message, 'credentials') => __('The mail server refused the username or password (authentication failed). Check the SMTP username and password.'),
            str_contains($message, 'getaddrinfo') || str_contains($message, 'name or service not known') || str_contains($message, 'php_network_getaddresses') || str_contains($message, 'no such host') => __('The mail server name could not be found. Check the SMTP host.'),
            str_contains($message, 'connection refused') => __('The mail server refused the connection. Check the host and port (Hostinger: smtp.hostinger.com, port 465 with SSL or 587 with TLS).'),
            str_contains($message, 'timed out') || str_contains($message, 'timeout') => __('The mail server did not answer in time (connection timed out). The host or port may be wrong, or the hosting blocks this port.'),
            str_contains($message, 'ssl') || str_contains($message, 'tls') || str_contains($message, 'certificate') || str_contains($message, 'crypto') => __('The secure connection failed. Check that the encryption matches the port: SSL for 465, TLS for 587.'),
            str_contains($message, '553') || str_contains($message, '550') || str_contains($message, 'sender') || str_contains($message, 'not owned') => __('The mail server refused the From address. It must be the same mailbox as the SMTP username.'),
            str_contains($message, 'expected response code') || str_contains($message, 'unable to connect') || str_contains($message, 'connection could not be established') => __('Could not talk to the mail server. Check the host, port and encryption.'),
            default => __('The email could not be sent.'),
        };
    }

    /**
     * The server's own words, shortened, with anything that looks like a
     * base64 credential removed.
     */
    public static function redact(string $message): string
    {
        $message = (string) preg_replace('/\b[A-Za-z0-9+\/]{24,}={0,2}/', '[…]', $message);

        return Str::limit(trim($message), 400);
    }
}

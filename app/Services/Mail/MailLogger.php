<?php

namespace App\Services\Mail;

use App\Models\MailLog;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Throwable;

/**
 * Writes Settings → Email's "Recent emails" (client report 2026-09-30):
 * every accepted email (MessageSent) and every failure the platform catches,
 * with the mail server's answer in plain words. Logging never breaks a send:
 * a database error here is swallowed.
 */
final class MailLogger
{
    /** Rows kept; older ones are pruned as new ones arrive. */
    public const int KEEP = 500;

    public function __construct(private readonly Config $config) {}

    public function sent(MessageSent $event): void
    {
        $message = $event->message;
        $mailable = $event->data['__laravel_mailable'] ?? null;
        $mailer = (string) $this->config->get('mail.default');

        $this->write([
            'to' => self::addresses($message->getTo()),
            'subject' => $message->getSubject(),
            'mailable' => is_string($mailable) ? class_basename($mailable) : null,
            'mailer' => $mailer,
            'status' => in_array($mailer, ['log', 'array'], true) ? MailLog::LOGGED : MailLog::SENT,
            'message_id' => $event->sent->getMessageId(),
        ]);
    }

    public function failed(?string $to, ?string $subject, ?string $mailable, Throwable|string $error): void
    {
        $detail = $error instanceof Throwable
            ? MailDelivery::explain($error).' ('.MailDelivery::redact($error->getMessage()).')'
            : $error;

        $this->write([
            'to' => $to,
            'subject' => $subject !== null ? Str::limit($subject, 250) : null,
            'mailable' => $mailable !== null ? class_basename($mailable) : null,
            'mailer' => (string) $this->config->get('mail.default'),
            'status' => MailLog::FAILED,
            'error' => Str::limit($detail, 1000),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function write(array $values): void
    {
        try {
            $log = MailLog::query()->create($values);

            if ($log->id % 50 === 0) {
                MailLog::query()->where('id', '<=', $log->id - self::KEEP)->delete();
            }
        } catch (Throwable) {
            // Never let the log break the email it describes.
        }
    }

    /**
     * @param  array<int, Address>  $addresses
     */
    private static function addresses(array $addresses): ?string
    {
        $list = implode(', ', array_map(fn (Address $address): string => $address->getAddress(), $addresses));

        return $list !== '' ? Str::limit($list, 250) : null;
    }
}

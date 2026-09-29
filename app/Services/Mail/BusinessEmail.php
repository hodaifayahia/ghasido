<?php

namespace App\Services\Mail;

use App\Services\Landing\LandingPageContentStore;

/**
 * The business email and sender name the Super Admin sets in Website
 * Management → Contact details (user request 2026-09-26). One address for
 * everything public: shown on the Contact Us page and in the footer,
 * where contact form messages are forwarded, and the sender of every email
 * GHASIDO sends (App\Listeners\ApplyBusinessSender).
 *
 * A mail server may only send for its own domain: mail sent as another
 * domain fails SPF/DMARC and is refused or lands in spam. So the business
 * email becomes the From address only when it shares the domain of the
 * server's sending address (MAIL_FROM_ADDRESS); otherwise the server's
 * address sends and replies still come back to the business email.
 *
 * Read fresh on every use, never cached across jobs, so a queue worker
 * picks up a change without a restart.
 */
final class BusinessEmail
{
    /** @var array<string, mixed>|null */
    private ?array $support = null;

    public function __construct(private readonly LandingPageContentStore $content) {}

    /** The business email, or null when none is set. */
    public function address(): ?string
    {
        $email = trim((string) ($this->support()['email'] ?? ''));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    /** The name recipients see next to the address. */
    public function senderName(): string
    {
        $name = trim((string) ($this->support()['sender_name'] ?? ''));

        return $name !== '' ? $name : self::serverName();
    }

    /** The address emails go out from. */
    public function fromAddress(): string
    {
        $business = $this->address();

        return $business !== null && $this->serverCanSendAs($business)
            ? $business
            : self::serverAddress();
    }

    /**
     * What the settings page shows about outgoing email.
     *
     * @return array{from: string, fromName: string, replyTo: string|null, sendsAsBusiness: bool, serverDomain: string, delivering: bool}
     */
    public function setup(): array
    {
        $business = $this->address();
        $from = $this->fromAddress();

        return [
            'from' => $from,
            'fromName' => $this->senderName(),
            'replyTo' => $business !== null && strcasecmp($business, $from) !== 0 ? $business : null,
            'sendsAsBusiness' => $business !== null && strcasecmp($business, $from) === 0,
            'serverDomain' => self::domain(self::serverAddress()),
            // The log and array mailers write mail down instead of sending it.
            'delivering' => ! in_array(config('mail.default'), ['log', 'array'], true),
        ];
    }

    /** The sending address configured on the server (MAIL_FROM_ADDRESS). */
    public static function serverAddress(): string
    {
        $address = config('mail.from.address');

        return is_string($address) ? trim($address) : '';
    }

    private function serverCanSendAs(string $email): bool
    {
        $server = self::serverAddress();

        return $server === '' || strcasecmp(self::domain($email), self::domain($server)) === 0;
    }

    private static function serverName(): string
    {
        $name = config('mail.from.name');

        return is_string($name) && trim($name) !== '' ? trim($name) : 'GHASIDO';
    }

    private static function domain(string $email): string
    {
        $at = strrpos($email, '@');

        return $at === false ? '' : strtolower(substr($email, $at + 1));
    }

    /** @return array<string, mixed> */
    private function support(): array
    {
        if ($this->support === null) {
            $support = $this->content->current('en')['support'] ?? [];
            $this->support = is_array($support) ? $support : [];
        }

        return $this->support;
    }
}

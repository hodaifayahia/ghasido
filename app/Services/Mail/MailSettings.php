<?php

namespace App\Services\Mail;

use App\Models\MailSetting;
use App\Models\User;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\QueryException;

/**
 * Settings → Email (client request 2026-09-29): the SMTP mailbox the
 * platform sends from, stored by the Super Admin, applied over .env.
 *
 * apply() rewrites config('mail.default'), config('mail.mailers.smtp'),
 * config('mail.from') and config('mail.reply_to') from the stored row. It
 * runs when the mail manager is first resolved on a web request, and again
 * before every queued job (MailSettingsServiceProvider), so a long-running
 * worker sends with what was saved a minute ago. With no row, the .env
 * values stay exactly as they were: tests keep MAIL_MAILER=array.
 *
 * The password is read here and handed to the transport only; it never
 * reaches a page, a log or the audit trail (SEC-03).
 *
 * @phpstan-type Values array{
 *     mailer: string,
 *     host: string,
 *     port: int,
 *     encryption: string,
 *     username: string,
 *     fromAddress: string,
 *     fromName: string,
 *     replyTo: string,
 *     sendImmediately: bool,
 * }
 */
final class MailSettings
{
    public const array MAILERS = ['smtp', 'log'];

    public const array ENCRYPTIONS = ['ssl', 'tls', 'none'];

    /** The client's Hostinger mailbox: the form starts from these. */
    public const string DEFAULT_HOST = 'smtp.hostinger.com';

    public const int DEFAULT_PORT = 465;

    public const string DEFAULT_ADDRESS = 'contact@ghasido.com';

    public const string DEFAULT_NAME = 'GHASIDO';

    /** Seconds before an unreachable server counts as a failure. */
    public const int TIMEOUT = 20;

    private ?MailSetting $row = null;

    private bool $loaded = false;

    public function __construct(private readonly Config $config) {}

    /**
     * The stored row, or null before the first save (or before migrate).
     */
    public function row(): ?MailSetting
    {
        if (! $this->loaded) {
            try {
                $this->row = MailSetting::query()->first();
            } catch (QueryException) {
                $this->row = null;
            }

            $this->loaded = true;
        }

        return $this->row;
    }

    /** Read the row again on the next call (after a save, before a job). */
    public function refresh(): void
    {
        $this->loaded = false;
        $this->row = null;
    }

    /**
     * Whether queued emails go out at once instead of waiting for a queue
     * worker. Only a saved row can turn it on; .env alone never does.
     */
    public function immediate(): bool
    {
        return $this->row()?->send_immediately === true;
    }

    /**
     * Rewrite config('mail.*') from the stored row. Without a row nothing
     * changes, so .env applies exactly as before.
     */
    public function apply(): void
    {
        $row = $this->row();

        if ($row === null) {
            return;
        }

        if ($row->mailer === 'smtp') {
            $this->config->set('mail.default', 'smtp');
            $this->config->set('mail.mailers.smtp', $this->transport($row));
        } else {
            $this->config->set('mail.default', 'log');
        }

        if (filled($row->from_address)) {
            $this->config->set('mail.from', [
                'address' => $row->from_address,
                'name' => filled($row->from_name) ? $row->from_name : self::DEFAULT_NAME,
            ]);
        }

        if (filled($row->reply_to)) {
            $this->config->set('mail.reply_to', [
                'address' => $row->reply_to,
                'name' => filled($row->from_name) ? $row->from_name : self::DEFAULT_NAME,
            ]);
        }
    }

    /**
     * The address people should write to: Reply-To, else From. Shown in the
     * footer of every email.
     */
    public function contactAddress(): string
    {
        $replyTo = $this->config->get('mail.reply_to.address');

        if (is_string($replyTo) && $replyTo !== '') {
            return $replyTo;
        }

        $from = $this->config->get('mail.from.address');

        return is_string($from) && $from !== '' ? $from : self::DEFAULT_ADDRESS;
    }

    /**
     * What Settings → Email shows: stored values (or the Hostinger
     * defaults), whether a password is stored, never the password.
     *
     * `env` is what .env says; it is only meaningful before the first save,
     * when nothing overrides it.
     *
     * @return array{values: Values, stored: bool, hasPassword: bool, env: array{mailer: string, host: string, fromAddress: string}, lastTest: array<string, mixed>|null, updatedAt: string|null}
     */
    public function payload(): array
    {
        $row = $this->row();

        $envMailer = $this->config->get('mail.default');
        $envHost = $this->config->get('mail.mailers.smtp.host');
        $envFrom = $this->config->get('mail.from.address');

        return [
            'values' => [
                'mailer' => $row !== null ? $row->mailer : 'smtp',
                'host' => $row->host ?? self::DEFAULT_HOST,
                'port' => $row->port ?? self::DEFAULT_PORT,
                'encryption' => $row !== null ? $row->encryption : 'ssl',
                'username' => $row->username ?? self::DEFAULT_ADDRESS,
                'fromAddress' => $row->from_address ?? self::DEFAULT_ADDRESS,
                'fromName' => $row->from_name ?? self::DEFAULT_NAME,
                'replyTo' => $row->reply_to ?? '',
                'sendImmediately' => $row === null || $row->send_immediately,
            ],
            'stored' => $row !== null,
            'hasPassword' => $row?->secret() !== null,
            'env' => [
                'mailer' => is_string($envMailer) ? $envMailer : 'log',
                'host' => is_string($envHost) ? $envHost : '',
                'fromAddress' => is_string($envFrom) ? $envFrom : '',
            ],
            'lastTest' => $row?->last_test,
            'updatedAt' => $row?->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Save the form. A blank password keeps the stored one.
     *
     * @param  array<string, mixed>  $validated
     */
    public function update(array $validated, User $by): MailSetting
    {
        $row = $this->row() ?? new MailSetting;

        $row->fill([
            'mailer' => (string) $validated['mailer'],
            'host' => self::nullableString($validated['host'] ?? null),
            'port' => isset($validated['port']) ? (int) $validated['port'] : null,
            'encryption' => (string) $validated['encryption'],
            'username' => self::nullableString($validated['username'] ?? null),
            'from_address' => (string) $validated['fromAddress'],
            'from_name' => (string) $validated['fromName'],
            'reply_to' => self::nullableString($validated['replyTo'] ?? null),
            'send_immediately' => (bool) ($validated['sendImmediately'] ?? false),
            'updated_by' => $by->id,
        ]);

        $password = $validated['password'] ?? null;

        if (is_string($password) && $password !== '') {
            $row->password = $password;
        }

        $row->save();

        $this->refresh();
        $this->apply();

        return $row;
    }

    /**
     * The values safe to keep in the audit log: everything but the password.
     *
     * @return array<string, mixed>
     */
    public static function auditValues(MailSetting $row): array
    {
        return [
            'mailer' => $row->mailer,
            'host' => $row->host,
            'port' => $row->port,
            'encryption' => $row->encryption,
            'username' => $row->username,
            'from_address' => $row->from_address,
            'from_name' => $row->from_name,
            'reply_to' => $row->reply_to,
            'send_immediately' => $row->send_immediately,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function recordTest(array $result): void
    {
        $row = $this->row();

        if ($row === null) {
            return;
        }

        $row->forceFill(['last_test' => $result])->saveQuietly();
    }

    /**
     * config('mail.mailers.smtp') for the stored mailbox. SSL (port 465) is
     * implicit TLS, `smtps`; TLS (587) is STARTTLS and is required; none
     * turns STARTTLS off.
     *
     * @return array<string, mixed>
     */
    private function transport(MailSetting $row): array
    {
        $appHost = parse_url((string) $this->config->get('app.url', 'http://localhost'), PHP_URL_HOST);

        return [
            'transport' => 'smtp',
            'scheme' => $row->encryption === 'ssl' ? 'smtps' : 'smtp',
            'url' => null,
            'host' => $row->host ?? self::DEFAULT_HOST,
            'port' => $row->port ?? self::DEFAULT_PORT,
            'username' => $row->username,
            'password' => $row->secret(),
            'timeout' => self::TIMEOUT,
            'local_domain' => is_string($appHost) && $appHost !== '' ? $appHost : null,
            'auto_tls' => $row->encryption !== 'none',
            'require_tls' => $row->encryption === 'tls',
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}

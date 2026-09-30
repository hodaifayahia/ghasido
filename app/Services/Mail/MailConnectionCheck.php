<?php

namespace App\Services\Mail;

use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Throwable;

/**
 * Settings → Email's step-by-step check (client report 2026-09-30:
 * "sending email does not work no matter what I did"). Before a test email
 * is sent, each step the mail server needs is tried on its own, so the
 * Super Admin sees WHICH one fails, in plain words:
 *
 *   1. server: the host name resolves;
 *   2. port: a connection opens on that port;
 *   3. login: the secure connection and the username/password work.
 *
 * When the port or the secure connection fails, the other usual Hostinger
 * pair (465 + SSL, 587 + TLS) is tried too and offered as a fix.
 *
 * @phpstan-type Step array{key: string, status: 'ok'|'failed'|'skipped', message: string, detail: string|null}
 * @phpstan-type Suggestion array{port: int, encryption: string}
 */
class MailConnectionCheck
{
    /** Seconds per connection attempt; the whole check stays under a minute. */
    public const int TIMEOUT = 10;

    /** @var list<array{int, string}> */
    private const array USUAL = [[465, 'ssl'], [587, 'tls']];

    public function __construct(private readonly MailSettings $settings) {}

    /**
     * @return array{ok: bool, steps: list<Step>, suggestion: Suggestion|null}
     */
    public function run(string $host, int $port, string $encryption, ?string $username, ?string $password): array
    {
        $steps = [];

        $resolved = $this->resolves($host);
        $steps[] = $resolved
            ? self::step('server', 'ok', __('The mail server :host was found.', ['host' => $host]))
            : self::step('server', 'failed', __('The mail server name :host could not be found. Check the SMTP host (Hostinger: smtp.hostinger.com).', ['host' => $host]));

        if (! $resolved) {
            return ['ok' => false, 'steps' => [...$steps, self::skipped('port'), self::skipped('login')], 'suggestion' => null];
        }

        $portError = $this->opens($host, $port);
        $steps[] = $portError === null
            ? self::step('port', 'ok', __('Port :port is open.', ['port' => $port]))
            : self::step('port', 'failed', __('Port :port could not be reached. The port may be wrong, or the hosting blocks it.', ['port' => $port]), $portError);

        if ($portError !== null) {
            return ['ok' => false, 'steps' => [...$steps, self::skipped('login')], 'suggestion' => $this->alternative($host, $port, $encryption, $username, $password)];
        }

        $loginError = $this->logsIn($host, $port, $encryption, $username, $password);

        if ($loginError === null) {
            $steps[] = self::step('login', 'ok', __('The secure connection and the login work.'));

            return ['ok' => true, 'steps' => $steps, 'suggestion' => null];
        }

        $steps[] = self::step('login', 'failed', MailDelivery::explain($loginError), MailDelivery::redact($loginError->getMessage()));

        // A refused password stays refused on another port; anything else
        // (wrong encryption for the port, a blocked port) may work there.
        $suggestion = self::isAuthentication($loginError)
            ? null
            : $this->alternative($host, $port, $encryption, $username, $password);

        return ['ok' => false, 'steps' => $steps, 'suggestion' => $suggestion];
    }

    protected function resolves(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        return gethostbyname($host) !== $host || checkdnsrr($host, 'A') || checkdnsrr($host, 'AAAA');
    }

    /**
     * Null when a TCP connection opens, else the system's reason.
     */
    protected function opens(string $host, int $port): ?string
    {
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client('tcp://'.$host.':'.$port, $errno, $errstr, self::TIMEOUT);

        if ($socket === false) {
            return MailDelivery::redact($errstr !== '' ? $errstr : 'connection failed ('.$errno.')');
        }

        fclose($socket);

        return null;
    }

    /**
     * Null when the SMTP handshake (TLS + AUTH) succeeds, else the error.
     */
    protected function logsIn(string $host, int $port, string $encryption, ?string $username, ?string $password): ?Throwable
    {
        $config = $this->settings->smtpConfig($host, $port, $encryption, $username, $password);

        try {
            $transport = (new EsmtpTransportFactory)->create(new Dsn(
                (string) $config['scheme'], $host, $username, $password, $port, $config,
            ));

            if (! $transport instanceof EsmtpTransport) {
                return null;
            }

            $stream = $transport->getStream();

            if ($stream instanceof SocketStream) {
                $stream->setTimeout(self::TIMEOUT);
            }

            $transport->start();
            $transport->stop();
        } catch (Throwable $exception) {
            return $exception;
        }

        return null;
    }

    /**
     * @return Suggestion|null
     */
    private function alternative(string $host, int $port, string $encryption, ?string $username, ?string $password): ?array
    {
        foreach (self::USUAL as [$tryPort, $tryEncryption]) {
            if ($tryPort === $port && $tryEncryption === $encryption) {
                continue;
            }

            if ($this->opens($host, $tryPort) === null && $this->logsIn($host, $tryPort, $tryEncryption, $username, $password) === null) {
                return ['port' => $tryPort, 'encryption' => $tryEncryption];
            }
        }

        return null;
    }

    private static function isAuthentication(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, '535') || str_contains($message, 'authenticat') || str_contains($message, 'credentials');
    }

    /**
     * @param  'ok'|'failed'|'skipped'  $status
     * @return Step
     */
    private static function step(string $key, string $status, string $message, ?string $detail = null): array
    {
        return ['key' => $key, 'status' => $status, 'message' => $message, 'detail' => $detail];
    }

    /**
     * @return Step
     */
    private static function skipped(string $key): array
    {
        return self::step($key, 'skipped', __('Not checked: an earlier step failed.'));
    }
}

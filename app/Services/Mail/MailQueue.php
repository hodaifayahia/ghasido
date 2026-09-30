<?php

namespace App\Services\Mail;

use App\Enums\ReminderStatus;
use App\Jobs\SendReminderEmail;
use Illuminate\Database\Query\Builder;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Emails stuck in the database queue (client report 2026-09-30: "sending
 * email does not work no matter what I did").
 *
 * On a host where no queue worker runs, or before "Send emails
 * immediately" was on, every email waited in `jobs` forever, and a wrong
 * password left them in `failed_jobs`. waiting() counts them for Settings →
 * Email, and flush() sends them now, inside the request, with the current
 * settings. Only email jobs are touched: AI, audio and export jobs stay on
 * the queue.
 */
final class MailQueue
{
    /** Emails sent per click, and the time budget for them. */
    public const int BATCH = 100;

    public const int SECONDS = 50;

    /** @var list<class-string> */
    private const array COMMANDS = [SendQueuedMailable::class, SendReminderEmail::class];

    private string $lastError = '';

    public function __construct(
        private readonly MailSettings $settings,
        private readonly MailLogger $logger,
    ) {}

    /**
     * @return array{waiting: int, failed: int}
     */
    public function counts(): array
    {
        try {
            return [
                'waiting' => $this->query('jobs')->count(),
                'failed' => $this->query('failed_jobs')->count(),
            ];
        } catch (Throwable) {
            return ['waiting' => 0, 'failed' => 0];
        }
    }

    /**
     * Send the waiting and failed emails now.
     *
     * @return array{sent: int, failed: int, left: int}
     */
    public function flush(): array
    {
        $this->settings->refresh();
        $this->settings->apply();
        app('mail.manager')->forgetMailers();

        $started = microtime(true);
        $sent = 0;
        $failed = 0;

        foreach (['jobs', 'failed_jobs'] as $table) {
            $rows = $this->query($table)->orderBy('id')->limit(self::BATCH)->get(['id', 'payload']);

            foreach ($rows as $row) {
                if ($sent + $failed >= self::BATCH || microtime(true) - $started > self::SECONDS) {
                    break 2;
                }

                // Claim the row first, so a worker that is running after all
                // never sends the same email twice.
                $claim = DB::table($table)->where('id', $row->id);

                if ($table === 'jobs') {
                    $claim->whereNull('reserved_at');
                }

                if ($claim->delete() !== 1) {
                    continue;
                }

                if ($this->run((string) $row->payload, $table === 'failed_jobs')) {
                    $sent++;

                    continue;
                }

                // Kept, so the next click (after fixing the settings) tries
                // it again: an email is never lost to a failed attempt.
                $this->keep((string) $row->payload);
                $failed++;
            }
        }

        $counts = $this->counts();

        return ['sent' => $sent, 'failed' => $failed, 'left' => $counts['waiting'] + $counts['failed']];
    }

    private function keep(string $payload): void
    {
        try {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) Str::uuid(),
                'connection' => 'database',
                'queue' => 'default',
                'payload' => $payload,
                'exception' => $this->lastError,
                'failed_at' => Date::now(),
            ]);
        } catch (Throwable) {
            // The failure is already in the mail log.
        }
    }

    private function run(string $payload, bool $retry): bool
    {
        try {
            $data = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
            $command = unserialize((string) ($data['data']['command'] ?? ''));

            if ($command instanceof SendReminderEmail) {
                $command->immediate = true;

                if ($retry && $command->reminder->status === ReminderStatus::Failed) {
                    $command->reminder->forceFill(['status' => ReminderStatus::Queued, 'blocked_reason' => null])->save();
                }

                app()->call([$command, 'handle']);

                $reminder = $command->reminder->fresh();
                $this->lastError = (string) ($reminder->blocked_reason ?? '');

                return $reminder?->status !== ReminderStatus::Failed;
            }

            if ($command instanceof SendQueuedMailable) {
                app()->call([$command, 'handle']);

                return true;
            }
        } catch (Throwable $exception) {
            $this->lastError = MailDelivery::redact($exception->getMessage());
            Log::warning('A waiting email could not be sent.', ['error' => MailDelivery::redact($exception->getMessage())]);
            $this->logger->failed(null, null, is_array($data ?? null) ? ($data['displayName'] ?? null) : null, $exception);

            return false;
        }

        return false;
    }

    private function query(string $table): Builder
    {
        return DB::table($table)->where(function ($query): void {
            foreach (self::COMMANDS as $command) {
                // The class name without its namespace: backslashes are
                // escaped differently in JSON, MySQL LIKE and SQLite LIKE.
                $query->orWhere('payload', 'like', '%'.class_basename($command).'%');
            }
        });
    }
}

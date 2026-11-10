<?php

namespace App\Console\Commands;

use App\Enums\ApiAccount;
use App\Services\Ai\AiModelSettings;
use App\Services\Owner\ApiCredit;
use App\Services\Owner\ApiKeyring;
use App\Services\Tts\DeepgramVoiceCatalog;
use App\Services\Tts\TtsSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Why is AI not working? (client report 2026-11-05: lesson generation,
 * images, role-play and voice checks all looked stopped.) Read-only: lists
 * the provider of each capability, whether its account has a key and
 * credit, and whether the queue that runs every AI job is being worked
 * (no AI call happens inside a request, AGENTS.md §6). Prints no secret.
 */
#[Signature('ai:doctor')]
#[Description('Diagnose why AI features are not running (providers, keys, credit, queue)')]
class AiDoctor extends Command
{
    public function handle(AiModelSettings $settings, ApiKeyring $keyring, ApiCredit $credit): int
    {
        $problems = 0;

        $this->info('Providers in use');
        foreach (['ai', 'image', 'tts', 'stt'] as $capability) {
            $this->line(sprintf('  %-6s %s', $capability, $settings->currentProvider($capability)));

            if ($settings->currentProvider($capability) === 'fake') {
                $this->warn(sprintf('    %s is on the fake provider: nothing real is generated. Set it in the .env (AI_PROVIDER, ...) or Settings → AI models.', $capability));
                $problems++;
            }
        }

        // The platform voice reads role-play replies, test audio and every
        // lesson without its own accent (client report 2026-11-10: an
        // Indian-accent voice was in use).
        $voice = app(TtsSettings::class)->voice();
        $voiceAccent = DeepgramVoiceCatalog::accentOf($voice);
        $this->line(sprintf('  voice  %s%s', $voice, $voiceAccent !== null ? ' ('.$voiceAccent.')' : ''));

        if ($voiceAccent !== null && ! in_array($voiceAccent, ['British', 'American'], true)) {
            $this->warn(sprintf('    The platform voice has a %s accent. Choose a British or American voice in AI Scenarios → Deepgram Voice Studio if that is not intended.', $voiceAccent));
            $problems++;
        }

        $this->newLine();
        $this->info('Accounts (owner console)');
        foreach (ApiAccount::cases() as $account) {
            $hasKey = $keyring->effective($account) !== '';
            $balance = $credit->balance($account);
            $state = $balance->state();
            $remaining = $balance->remainingUsd();

            $this->line(sprintf(
                '  %-9s key: %-7s credit: %-9s %s',
                $account->label(),
                $hasKey ? 'set' : 'MISSING',
                $state,
                $remaining === null ? '' : sprintf('($%s left)', number_format($remaining, 2)),
            ));

            if (! $hasKey) {
                $this->warn(sprintf('    No %s key: save it on /owner or set it in .env.', $account->label()));
                $problems++;
            }

            if (in_array($state, ['paused', 'exhausted'], true)) {
                $this->warn(sprintf('    %s is %s: recharge or resume it on /owner, or every %s call is refused.', $account->label(), $state, $account->label()));
                $problems++;
            }
        }

        $this->newLine();
        $this->info('Queue (every AI job runs on it)');
        $this->line('  connection: '.(string) config('queue.default'));

        try {
            $pending = DB::table('jobs')->count();
            $oldest = DB::table('jobs')->min('created_at');
            $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();

            $this->line(sprintf('  waiting jobs: %d', $pending));
            $this->line(sprintf('  failed in the last 24h: %d', $failed));

            if ($pending > 0 && is_numeric($oldest) && (int) $oldest < now()->subMinutes(5)->getTimestamp()) {
                $this->warn(sprintf('    The oldest job has waited %d minutes: no queue worker is running. The site now works through the queue itself after each signed-in page load, but add the two queue cron jobs in hPanel (docs/deploy/hostinger.md) so AI work never waits for someone to open a page.', intdiv(now()->getTimestamp() - (int) $oldest, 60)));
                $problems++;
            }

            if ($failed > 0) {
                $this->warn(sprintf('    %d AI job(s) failed in the last 24 hours. Grouped by job, with what the provider answered:', $failed));
                $problems++;
            }

            // Grouped by job, newest first, with the provider's own answer
            // (the line after "status code 403:"), so a refused key or a
            // model the account may not use is visible here, not only in
            // the log (client report 2026-11-08).
            DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->orderByDesc('id')
                ->limit(200)
                ->get(['failed_at', 'payload', 'exception'])
                ->groupBy(fn (object $row): string => self::jobName((string) $row->payload))
                ->each(function ($rows, string $job): void {
                    $latest = $rows->first();
                    $this->line(sprintf('    %s ×%d (last %s)', $job, $rows->count(), $latest->failed_at));
                    $this->line('      '.self::reason((string) $latest->exception));
                });
        } catch (Throwable $exception) {
            $this->warn('  Could not read the queue tables: '.$exception->getMessage());
            $problems++;
        }

        $this->newLine();

        if ($problems === 0) {
            $this->info('No problem found. If a feature still fails, check storage/logs/laravel-*.log and the queue logs.');

            return self::SUCCESS;
        }

        $this->error(sprintf('%d problem(s) found, see above.', $problems));

        return self::FAILURE;
    }

    /** The job class from a queued payload, e.g. "GenerateContentImage". */
    private static function jobName(string $payload): string
    {
        $decoded = json_decode($payload, true);
        $name = is_array($decoded) && is_string($decoded['displayName'] ?? null) ? $decoded['displayName'] : 'Unknown job';

        return class_basename($name);
    }

    /**
     * The first line of the error plus the provider's answer that follows
     * it, on one line, with anything that looks like a key masked.
     */
    private static function reason(string $exception): string
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $exception)), fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#') && ! str_starts_with($line, 'Stack trace')));
        $text = implode(' ', array_slice($lines, 0, 3));
        $text = (string) preg_replace('/(sk-[A-Za-z0-9_-]{4})[A-Za-z0-9_-]+/', '$1…', $text);

        return Str::limit($text, 400);
    }
}

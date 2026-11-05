<?php

namespace App\Console\Commands;

use App\Enums\ApiAccount;
use App\Services\Ai\AiModelSettings;
use App\Services\Owner\ApiCredit;
use App\Services\Owner\ApiKeyring;
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
                $this->warn(sprintf('    The oldest job has waited %d minutes: no queue worker is running. Add the two queue cron jobs in hPanel (docs/deploy/hostinger.md).', intdiv(now()->getTimestamp() - (int) $oldest, 60)));
                $problems++;
            }

            DB::table('failed_jobs')->orderByDesc('id')->limit(5)->get(['failed_at', 'exception'])
                ->each(function (object $row): void {
                    $this->line('    '.$row->failed_at.'  '.Str::limit(strtok((string) $row->exception, "\n") ?: '', 180));
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
}

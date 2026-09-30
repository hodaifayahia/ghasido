<?php

namespace App\Console\Commands;

use App\Enums\ApiAccount;
use App\Services\Ai\AiModelSettings;
use App\Services\Ai\UsageLedgerRepair;
use App\Services\Owner\ApiKeyring;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

/**
 * Report what the AI usage ledger holds, and put right the rows written
 * before every call was metered under the account that billed it (API-03,
 * AIL-04; spec 0007). Idempotent: run it as often as you like; `--dry-run`
 * only reports.
 */
#[Signature('ai:usage-repair {--dry-run : Report what would change and change nothing} {--reprice : Also stamp today\'s price on real rows stored at $0}')]
#[Description('Audit the AI usage ledger and relabel, close and price what was missed')]
class RepairAiUsage extends Command
{
    public function handle(UsageLedgerRepair $repair, AiModelSettings $settings, ApiKeyring $keyring): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Providers in use now: '.implode(', ', array_map(
            fn (string $capability): string => $capability.'='.$settings->currentProvider($capability),
            ['ai', 'image', 'tts', 'stt'],
        )));

        foreach (ApiAccount::cases() as $account) {
            $since = $keyring->setting($account)?->metering_started_at;
            $this->line(sprintf('%s credit metered since: %s', $account->label(), $since?->toDateTimeString() ?? 'no recharge yet (no limit)'));
        }

        $this->newLine();
        $this->line($dryRun ? 'Dry run: nothing is changed.' : 'Repairing:');
        $this->line(sprintf('- lesson audio rows relabelled to the real provider: %d', $repair->relabelSpeech($dryRun)));
        $this->line(sprintf('- transcription rows relabelled to the real provider: %d', $repair->relabelTranscriptions($dryRun)));
        $this->line(sprintf('- fake rows whose cost was cleared: %d', $repair->clearFakeCosts($dryRun)));
        $this->line(sprintf('- open voice calls closed and metered: %d', $repair->closeStaleVoiceCalls($dryRun)));

        if ($this->option('reprice')) {
            $this->line(sprintf('- rows priced at today\'s prices: %d', $repair->reprice($dryRun)));
        }

        $this->newLine();
        $from = CarbonImmutable::instance(Date::now())->startOfMonth();
        $this->info('This month\'s ledger ('.$from->toDateString().' onwards):');

        $rows = $repair->summary($from);

        if ($rows === []) {
            $this->line('No AI usage recorded this month.');

            return self::SUCCESS;
        }

        $this->table(
            ['Provider', 'Account', 'Feature', 'Model', 'Calls', 'Units', 'Stored cost', 'Points', 'Priced'],
            array_map(fn (array $row): array => [
                $row['provider'],
                $row['account'],
                $row['feature'],
                $row['model'],
                $row['calls'],
                $row['units'],
                number_format($row['cost'], 6),
                $row['points'],
                $row['priced'] ? 'yes' : 'NO',
            ], $rows),
        );

        return self::SUCCESS;
    }
}

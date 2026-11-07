<?php

namespace App\Console\Commands;

use App\Enums\ApiAccount;
use App\Models\ApiAccountSetting;
use App\Models\AuditLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

/**
 * Save a paid API account's key from the server's command line (user
 * request 2026-11-07), exactly as the owner console's key form does: stored
 * encrypted with APP_KEY, used by the next AI call, and it takes priority
 * over the .env key. The key is typed at a hidden prompt, so it never
 * lands in the shell history or the process list (SEC-03); the audit row
 * keeps only its last four characters (SEC-06).
 *
 *     php artisan ai:set-key anthropic
 */
#[Signature('ai:set-key {account=anthropic : anthropic, qwen or deepgram} {--clear : Remove the stored key and fall back to the .env key}')]
#[Description('Save (or clear) the API key of the Claude, Qwen or Deepgram account')]
class SetApiKey extends Command
{
    public function handle(): int
    {
        $account = ApiAccount::tryFrom(strtolower((string) $this->argument('account')));

        if ($account === null) {
            $this->error('Unknown account. Use one of: '.implode(', ', array_map(fn (ApiAccount $a): string => $a->value, ApiAccount::cases())).'.');

            return self::FAILURE;
        }

        $setting = ApiAccountSetting::for($account);

        if ($this->option('clear')) {
            $setting->forceFill(['api_key' => null, 'key_updated_at' => Date::now()])->save();
            AuditLog::record($setting, 'api_key.cleared', ['account' => $account->value, 'via' => 'cli']);
            $this->info($account->label().' now uses the key from the .env file.');

            return self::SUCCESS;
        }

        $key = trim((string) $this->secret('Paste the '.$account->label().' API key (it is not shown)'));

        if ($key === '' || mb_strlen($key) < 20 || preg_match('/\s/', $key) === 1) {
            $this->error('That does not look like an API key. Nothing was changed.');

            return self::FAILURE;
        }

        if ($account === ApiAccount::Anthropic && ! str_starts_with($key, 'sk-ant-')) {
            $this->warn('Anthropic API keys usually start with "sk-ant-". Saving it anyway.');
        }

        $setting->forceFill(['api_key' => $key, 'key_updated_at' => Date::now()])->save();
        AuditLog::record($setting, 'api_key.replaced', ['account' => $account->value, 'ends_with' => mb_substr($key, -4), 'via' => 'cli']);

        $this->info($account->label().' key saved (ends with '.mb_substr($key, -4).'). New AI calls use it now.');
        $this->line('Check it with: php artisan ai:doctor');

        return self::SUCCESS;
    }
}

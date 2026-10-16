<?php

namespace App\Services\Owner;

use App\Enums\ApiAccount;
use App\Models\ApiAccountSetting;
use App\Services\Ai\AiModelSettings;
use Illuminate\Database\QueryException;

/**
 * The API key in effect for each provider config slot (spec 0007, D2/D3).
 *
 * The platform owner can store a key per account on the owner console; a
 * stored key replaces the .env value for the slots that account serves,
 * and clearing it falls back to .env. Callers ask here instead of reading
 * config('services.*.key') so a key saved now is used by the next job,
 * even in a long-running queue worker. Keys never leave the server
 * (API-02, SEC-03): the console gets masked() only.
 *
 * Registered as a scoped instance: the rows are read once per request or
 * queued job, and forget() drops them after a write.
 */
final class ApiKeyring
{
    /**
     * Config slot => [the config key naming the slot's provider (null: one
     * fixed account), provider => the account whose owner key applies]. The
     * text slot follows AI_PROVIDER: Claude's key for `anthropic`, Qwen's
     * for `qwen`. A slot pointing at another vendor keeps its .env key.
     *
     * @var array<string, array{0: string|null, 1: array<string, string>}>
     */
    private const array SLOTS = [
        'services.ai.key' => ['services.ai.provider', ['anthropic' => 'anthropic', 'qwen' => 'qwen', 'fake' => 'anthropic']],
        'services.ai.image_key' => ['services.ai.image_provider', ['qwen' => 'qwen', 'dashscope' => 'qwen', 'fake' => 'qwen']],
        'services.tts.key' => ['services.tts.provider', ['deepgram' => 'deepgram', 'fake' => 'deepgram']],
        'services.stt.key' => ['services.stt.provider', ['deepgram' => 'deepgram', 'fake' => 'deepgram']],
        'services.voice_agent.key' => [null, ['*' => 'deepgram']],
    ];

    /**
     * The slots whose .env value stands for the account on the console, in
     * order: the first one that belongs to the account now.
     */
    private const array PRIMARY = [
        'anthropic' => ['services.ai.key'],
        'qwen' => ['services.ai.key', 'services.ai.image_key'],
        'deepgram' => ['services.voice_agent.key'],
    ];

    /** @var array<string, ApiAccountSetting>|null */
    private ?array $settings = null;

    /**
     * The key a provider config slot resolves to now.
     */
    public function key(string $configKey): string
    {
        $account = self::accountOf($configKey);

        if ($account !== null) {
            $stored = $this->stored($account);

            if ($stored !== null) {
                return $stored;
            }
        }

        return self::env($configKey);
    }

    /**
     * The key the owner stored for an account, or null.
     */
    public function stored(ApiAccount $account): ?string
    {
        return $this->setting($account)?->key();
    }

    /**
     * Where the account's key comes from: `owner` (stored on the console),
     * `env` (.env) or `none`.
     */
    public function source(ApiAccount $account): string
    {
        if ($this->stored($account) !== null) {
            return 'owner';
        }

        return self::primaryEnv($account) !== '' ? 'env' : 'none';
    }

    /**
     * The key in effect for the account, masked to its last four
     * characters, or null when there is none.
     */
    public function masked(ApiAccount $account): ?string
    {
        $key = $this->stored($account) ?? self::primaryEnv($account);

        if ($key === '') {
            return null;
        }

        return '••••'.(mb_strlen($key) > 8 ? mb_substr($key, -4) : '');
    }

    /**
     * The key in effect for the account's primary slot (for the owner's
     * live balance lookup).
     */
    public function effective(ApiAccount $account): string
    {
        return $this->stored($account) ?? self::primaryEnv($account);
    }

    /**
     * The account's stored row, or null before its first write or when the
     * table is not there yet (a fresh clone before migrate).
     */
    public function setting(ApiAccount $account): ?ApiAccountSetting
    {
        if ($this->settings === null) {
            try {
                $this->settings = ApiAccountSetting::query()->get()
                    ->keyBy(fn (ApiAccountSetting $row): string => $row->account->value)
                    ->all();
            } catch (QueryException) {
                $this->settings = [];
            }
        }

        return $this->settings[$account->value] ?? null;
    }

    /**
     * Drop what was read, after the owner changed a row.
     */
    public function forget(): void
    {
        $this->settings = null;
    }

    /**
     * The account whose owner key a config slot uses now, or null when the
     * slot points at a vendor the owner does not fund.
     */
    private static function accountOf(string $configKey): ?ApiAccount
    {
        $slot = self::SLOTS[$configKey] ?? null;

        if ($slot === null) {
            return null;
        }

        if ($slot[0] === null) {
            return ApiAccount::from($slot[1]['*']);
        }

        $provider = config($slot[0]);
        $provider = is_string($provider) && trim($provider) !== '' ? trim($provider) : 'fake';

        // `openai` pointed at Alibaba's host is the Qwen account.
        if ($slot[0] === 'services.ai.provider') {
            $provider = AiModelSettings::aiProviderLabel($provider);
        }

        $account = $slot[1][$provider] ?? null;

        return $account === null ? null : ApiAccount::from($account);
    }

    /**
     * The .env key standing for the account: the first of its primary
     * slots that belongs to it now, so Qwen never shows Claude's key.
     */
    private static function primaryEnv(ApiAccount $account): string
    {
        foreach (self::PRIMARY[$account->value] as $configKey) {
            if (self::accountOf($configKey) === $account) {
                return self::env($configKey);
            }
        }

        return '';
    }

    private static function env(string $configKey): string
    {
        $value = config($configKey);

        return is_string($value) ? trim($value) : '';
    }
}

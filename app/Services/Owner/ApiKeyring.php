<?php

namespace App\Services\Owner;

use App\Enums\ApiAccount;
use App\Models\ApiAccountSetting;
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
     * Config slot => [account, the config key naming the slot's provider,
     * the providers for which the owner's key applies]. A slot pointing at
     * another vendor (AI_PROVIDER=anthropic) keeps its own .env key.
     *
     * @var array<string, array{0: string, 1: string|null, 2: list<string>}>
     */
    private const array SLOTS = [
        'services.ai.key' => ['qwen', 'services.ai.provider', ['qwen', 'fake']],
        'services.ai.image_key' => ['qwen', 'services.ai.image_provider', ['qwen', 'dashscope', 'fake']],
        'services.tts.key' => ['deepgram', 'services.tts.provider', ['deepgram', 'fake']],
        'services.stt.key' => ['deepgram', 'services.stt.provider', ['deepgram', 'fake']],
        'services.voice_agent.key' => ['deepgram', null, []],
    ];

    /** The slot whose .env value stands for the account on the console. */
    private const array PRIMARY = [
        'qwen' => 'services.ai.key',
        'deepgram' => 'services.voice_agent.key',
    ];

    /** @var array<string, ApiAccountSetting>|null */
    private ?array $settings = null;

    /**
     * The key a provider config slot resolves to now.
     */
    public function key(string $configKey): string
    {
        $slot = self::SLOTS[$configKey] ?? null;

        if ($slot !== null && self::applies($slot)) {
            $stored = $this->stored(ApiAccount::from($slot[0]));

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

        return self::env(self::PRIMARY[$account->value]) !== '' ? 'env' : 'none';
    }

    /**
     * The key in effect for the account, masked to its last four
     * characters, or null when there is none.
     */
    public function masked(ApiAccount $account): ?string
    {
        $key = $this->stored($account) ?? self::env(self::PRIMARY[$account->value]);

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
        return $this->key(self::PRIMARY[$account->value]);
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
     * @param  array{0: string, 1: string|null, 2: list<string>}  $slot
     */
    private static function applies(array $slot): bool
    {
        if ($slot[1] === null) {
            return true;
        }

        $provider = config($slot[1]);
        $provider = is_string($provider) && trim($provider) !== '' ? trim($provider) : 'fake';

        return in_array($provider, $slot[2], true);
    }

    private static function env(string $configKey): string
    {
        $value = config($configKey);

        return is_string($value) ? trim($value) : '';
    }
}

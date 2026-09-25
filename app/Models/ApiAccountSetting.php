<?php

namespace App\Models;

use App\Enums\ApiAccount;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One paid API account's owner-side settings (spec 0007): the key that
 * overrides .env, whether the owner paused it, and when credit metering
 * started. One row per ApiAccount, created on first write.
 *
 * The key is encrypted with APP_KEY and hidden from serialisation; the
 * console only ever shows its last four characters (API-02, SEC-03).
 *
 * @property int $id
 * @property ApiAccount $account
 * @property string|null $api_key
 * @property Carbon|null $key_updated_at
 * @property Carbon|null $paused_at
 * @property Carbon|null $metering_started_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['account', 'api_key', 'key_updated_at', 'paused_at', 'metering_started_at'])]
#[Hidden(['api_key'])]
class ApiAccountSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account' => ApiAccount::class,
            'api_key' => 'encrypted',
            'key_updated_at' => 'datetime',
            'paused_at' => 'datetime',
            'metering_started_at' => 'datetime',
        ];
    }

    public static function for(ApiAccount $account): self
    {
        return self::query()->firstOrNew(['account' => $account->value]);
    }

    /**
     * The stored key, or null when none is stored or it no longer decrypts
     * (APP_KEY rotated): the account then falls back to .env.
     */
    public function key(): ?string
    {
        try {
            // Through getAttribute(): the cast decrypts, and throws, here.
            $key = $this->getAttribute('api_key');
        } catch (DecryptException) {
            return null;
        }

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }
}

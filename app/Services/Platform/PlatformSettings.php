<?php

namespace App\Services\Platform;

use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\User;

/**
 * Platform-wide learning settings the Super Admin changes without a
 * developer (ADM-02; client request 2026-09-30). Each falls back to its
 * config default until it is first saved.
 */
final class PlatformSettings
{
    public const LEVEL_UP_FROM = 'levels.level_up_from';

    /** @var array<string, array<string, mixed>|null> */
    private array $cache = [];

    /**
     * The Pre-test percentage at or above which the next level is suggested
     * (client request 2026-09-30: 70% by default, set by the Super Admin).
     */
    public function levelUpFrom(): int
    {
        $stored = $this->get(self::LEVEL_UP_FROM)['percent'] ?? null;

        return is_numeric($stored)
            ? max(1, min(100, (int) $stored))
            : (int) config('guesvia.levels.level_up_from', 70);
    }

    public function setLevelUpFrom(int $percent, User $actor): void
    {
        $this->put(self::LEVEL_UP_FROM, ['percent' => max(1, min(100, $percent))], $actor);
    }

    /** @return array<string, mixed>|null */
    public function get(string $key): ?array
    {
        if (! array_key_exists($key, $this->cache)) {
            $value = PlatformSetting::query()->where('key', $key)->value('value');
            $this->cache[$key] = is_array($value) ? $value : (is_string($value) ? json_decode($value, true) : null);
        }

        return $this->cache[$key];
    }

    /** @param array<string, mixed> $value */
    public function put(string $key, array $value, User $actor): void
    {
        $setting = PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by' => $actor->id],
        );

        $this->cache[$key] = $value;

        AuditLog::record($setting, 'platform-setting.updated', ['key' => $key, 'value' => $value]);
    }
}

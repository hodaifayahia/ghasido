<?php

namespace App\Services\Scenarios;

use App\Models\AiScenarioConfiguration;
use App\Models\AuditLog;
use App\Models\User;

/**
 * Reads and writes the global controls shown in the AI Scenarios tabs. The
 * fallback payload keeps a new install usable before an admin saves a value;
 * every subsequent edit is persisted and audited (ADM-02, RP-04, AIE-04).
 */
final class ScenarioConfiguration
{
    public const CATEGORIES = 'categories';

    public const INSTRUCTIONS = 'instructions';

    public const FEEDBACK = 'feedback';

    /**
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    public function get(string $key, array $fallback): array
    {
        $value = AiScenarioConfiguration::query()->where('key', $key)->first()?->value;

        return is_array($value) && $value !== [] ? $value : $fallback;
    }

    /** @param array<string, mixed> $value */
    public function put(string $key, array $value, User $actor): void
    {
        $configuration = AiScenarioConfiguration::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by' => $actor->id],
        );

        AuditLog::record($configuration, 'ai-scenario.configuration.updated', [
            'key' => $key,
        ]);
    }
}

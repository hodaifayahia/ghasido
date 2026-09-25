<?php

namespace App\Services\Owner;

use App\Enums\ApiAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The real balance left on the Deepgram account (spec 0007), read on the
 * owner's request from Deepgram's management API: the first project the
 * key can see, then that project's balances. The last answer is kept in
 * the cache for the console; nothing here is ever sent to an app user.
 *
 * Qwen has no equivalent: Alibaba's billing API needs a signed AccessKey,
 * not the model key, so its credit is the owner's own ledger only.
 */
final class DeepgramBalance
{
    public const string BASE_URL = 'https://api.deepgram.com/v1';

    private const string CACHE_KEY = 'owner-console.deepgram-balance';

    public function __construct(private readonly ApiKeyring $keyring) {}

    /**
     * Ask Deepgram now and keep the answer.
     *
     * @return array{ok: bool, project: string|null, balances: list<array{amount: float, units: string}>, error: string|null, fetchedAt: string}
     */
    public function refresh(): array
    {
        $result = $this->fetch($this->keyring->effective(ApiAccount::Deepgram));
        Cache::put(self::CACHE_KEY, $result, now()->addDay());

        return $result;
    }

    /**
     * The last answer, or null before the first request.
     *
     * @return array{ok: bool, project: string|null, balances: list<array{amount: float, units: string}>, error: string|null, fetchedAt: string}|null
     */
    public function last(): ?array
    {
        try {
            $value = Cache::get(self::CACHE_KEY);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($value) || ! is_bool($value['ok'] ?? null)) {
            return null;
        }

        /** @var array{ok: bool, project: string|null, balances: list<array{amount: float, units: string}>, error: string|null, fetchedAt: string} $value */
        return $value;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{ok: bool, project: string|null, balances: list<array{amount: float, units: string}>, error: string|null, fetchedAt: string}
     */
    private function fetch(string $key): array
    {
        $fail = fn (string $error): array => [
            'ok' => false,
            'project' => null,
            'balances' => [],
            'error' => $error,
            'fetchedAt' => Date::now()->toIso8601String(),
        ];

        if ($key === '') {
            return $fail(__('No Deepgram key is set.'));
        }

        $client = Http::baseUrl(self::BASE_URL)
            ->withHeaders(['Authorization' => 'Token '.$key])
            ->acceptJson()
            ->connectTimeout(8)
            ->timeout(15);

        try {
            $projects = $client->get('/projects');

            if (! $projects->successful()) {
                return $fail(self::refusal($projects->status()));
            }

            $project = $projects->json('projects.0');

            if (! is_array($project) || ! is_string($project['project_id'] ?? null)) {
                return $fail(__('This Deepgram key cannot see any project.'));
            }

            $response = $client->get('/projects/'.rawurlencode($project['project_id']).'/balances');
        } catch (ConnectionException) {
            return $fail(__('Could not reach Deepgram. Please try again.'));
        }

        if (! $response->successful()) {
            return $fail(self::refusal($response->status()));
        }

        $balances = [];

        foreach ((array) $response->json('balances', []) as $balance) {
            if (is_array($balance) && is_numeric($balance['amount'] ?? null)) {
                $balances[] = [
                    'amount' => round((float) $balance['amount'], 4),
                    'units' => is_string($balance['units'] ?? null) ? strtoupper($balance['units']) : 'USD',
                ];
            }
        }

        return [
            'ok' => true,
            'project' => is_string($project['name'] ?? null) ? $project['name'] : $project['project_id'],
            'balances' => $balances,
            'error' => null,
            'fetchedAt' => Date::now()->toIso8601String(),
        ];
    }

    private static function refusal(int $status): string
    {
        return in_array($status, [401, 403], true)
            ? __('Deepgram refused the key (HTTP :status). Reading the balance needs a key with billing access (Owner or Admin role).', ['status' => $status])
            : __('Deepgram answered HTTP :status.', ['status' => $status]);
    }
}

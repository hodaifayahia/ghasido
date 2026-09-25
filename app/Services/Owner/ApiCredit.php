<?php

namespace App\Services\Owner;

use App\Enums\AiFeature;
use App\Enums\ApiAccount;
use App\Models\AiModelPrice;
use App\Models\AiUsage;
use App\Models\ApiCreditTopup;
use App\Services\Ai\AiLimitReached;
use App\Services\Ai\AiModelSettings;
use App\Services\Ai\AiUsageReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;

/**
 * The platform owner's credit per paid API account, and the gate that
 * stops the app calling an account once it is spent or paused (spec 0007,
 * D4–D7; AIL-03, AIL-04, API-03).
 *
 * Credit = the sum of the owner's recharges (api_credit_topups). Spend =
 * the cost of every `ai_usages` row that bills to the account since its
 * first recharge, with unpriced rows costed at today's price like the AI
 * usage report does. Nothing is cached across requests: rows are summed
 * live, once per request or queued job (scoped instance).
 *
 * An account with no recharge has no limit, so a deploy never switches AI
 * off before the owner has set anything (D6).
 */
final class ApiCredit
{
    /** @var array<string, CreditBalance> */
    private array $balances = [];

    /** @var array<string, array{usd: float, tokens: int, limitedByUsd: bool, limitedByTokens: bool}> */
    private array $grants = [];

    public function __construct(private readonly ApiKeyring $keyring) {}

    public function balance(ApiAccount $account): CreditBalance
    {
        return $this->balances[$account->value] ??= $this->compute($account);
    }

    /**
     * Whether the app may call the account now. Cheap when no limit is
     * set: the usage ledger is only summed for a limited account.
     */
    public function isAvailable(ApiAccount $account): bool
    {
        try {
            if ($this->keyring->setting($account)?->paused_at !== null) {
                return false;
            }

            $grants = $this->grants($account);

            if (! $grants['limitedByUsd'] && ! $grants['limitedByTokens']) {
                return true;
            }

            return ! $this->balance($account)->blocked();
        } catch (QueryException) {
            // Tables not there yet (fresh clone before migrate): no limit.
            return true;
        }
    }

    /**
     * @throws AiLimitReached when any of the accounts is spent or paused
     */
    public function assertAvailable(ApiAccount ...$accounts): void
    {
        foreach ($accounts as $account) {
            if (! $this->isAvailable($account)) {
                throw AiLimitReached::forCredit($account, $this->balance($account)->paused);
            }
        }
    }

    /**
     * The hard stop in the provider bindings (D7b): refuse to build a
     * provider whose account is blocked, so no queued job can spend.
     *
     * @throws AiLimitReached
     */
    public function assertProvider(string $provider): void
    {
        $account = ApiAccount::forProvider($provider);

        if ($account !== null) {
            $this->assertAvailable($account);
        }
    }

    /**
     * The account a capability runs on now (`ai`, `fast`, `image`, `tts`,
     * `stt`, or `voice` for the Deepgram voice agent), after the Super
     * Admin's fake/real switches; null for fake or another vendor.
     */
    public function accountFor(string $capability): ?ApiAccount
    {
        if ($capability === 'voice') {
            return ApiAccount::Deepgram;
        }

        [$switch, $configKey] = match ($capability) {
            'ai', 'fast' => ['ai', 'services.ai.provider'],
            'image' => ['image', 'services.ai.image_provider'],
            'tts' => ['tts', 'services.tts.provider'],
            'stt' => ['stt', 'services.stt.provider'],
            default => [null, null],
        };

        if ($switch === null) {
            return null;
        }

        $env = config($configKey);
        $env = is_string($env) && trim($env) !== '' ? trim($env) : 'fake';

        return ApiAccount::forProvider(app(AiModelSettings::class)->provider($switch, $env));
    }

    /**
     * The pre-dispatch check (D7a) for the capabilities a feature needs.
     *
     * @throws AiLimitReached
     */
    public function assertCapabilities(string ...$capabilities): void
    {
        foreach ($capabilities as $capability) {
            $account = $this->accountFor($capability);

            if ($account !== null) {
                $this->assertAvailable($account);
            }
        }
    }

    /**
     * Drop the balances read so far, after a recharge or a pause.
     */
    public function forget(): void
    {
        $this->balances = [];
        $this->grants = [];
        $this->keyring->forget();
    }

    /**
     * Spend since metering started, by feature, for the console.
     *
     * @return list<array{feature: string, label: string, calls: int, cost: float, units: int}>
     */
    public function spendByFeature(ApiAccount $account): array
    {
        $prices = AiModelPrice::byModel();
        $rows = $this->usage($account)
            ->groupBy('feature', 'model')
            ->toBase()
            ->selectRaw('feature, model, count(*) as calls, sum(cost_estimate) as cost, sum(prompt_tokens + completion_tokens) as units, sum(case when cost_estimate > 0 then 0 else prompt_tokens end) as unpriced_prompt, sum(case when cost_estimate > 0 then 0 else completion_tokens end) as unpriced_completion')
            ->get();

        $features = [];

        foreach ($rows as $row) {
            $key = (string) $row->feature;
            $estimate = AiModelPrice::lookup($prices, (string) $row->model)?->costOf((int) $row->unpriced_prompt, (int) $row->unpriced_completion) ?? 0.0;
            $features[$key]['calls'] = ($features[$key]['calls'] ?? 0) + (int) $row->calls;
            $features[$key]['cost'] = ($features[$key]['cost'] ?? 0.0) + (float) $row->cost + $estimate;
            $features[$key]['units'] = ($features[$key]['units'] ?? 0) + (int) $row->units;
        }

        $list = [];

        foreach ($features as $key => $totals) {
            $feature = AiFeature::tryFrom($key);
            $list[] = [
                'feature' => $key,
                'label' => $feature === null ? $key : AiUsageReport::featureLabel($feature),
                'calls' => $totals['calls'],
                'cost' => round($totals['cost'], 4),
                'units' => $totals['units'],
            ];
        }

        usort($list, fn (array $a, array $b): int => [$b['cost'], $b['calls']] <=> [$a['cost'], $a['calls']]);

        return $list;
    }

    private function compute(ApiAccount $account): CreditBalance
    {
        $setting = $this->keyring->setting($account);
        $grants = $this->grants($account);

        $prices = AiModelPrice::byModel();
        $rows = $this->usage($account)
            ->groupBy('model')
            ->toBase()
            ->selectRaw("model, min(feature) as feature, count(*) as calls, sum(cost_estimate) as cost, sum(case when feature = '".AiFeature::ImageGenerate->value."' then 0 else prompt_tokens + completion_tokens end) as tokens, sum(case when cost_estimate > 0 then 0 else prompt_tokens end) as unpriced_prompt, sum(case when cost_estimate > 0 then 0 else completion_tokens end) as unpriced_completion")
            ->get();

        $spentUsd = 0.0;
        $spentTokens = 0;
        $calls = 0;
        $unpriced = [];

        foreach ($rows as $row) {
            $model = (string) $row->model;
            $price = AiModelPrice::lookup($prices, $model);
            $unpricedUnits = (int) $row->unpriced_prompt + (int) $row->unpriced_completion;

            $spentUsd += (float) $row->cost + ($price?->costOf((int) $row->unpriced_prompt, (int) $row->unpriced_completion) ?? 0.0);
            $spentTokens += (int) $row->tokens;
            $calls += (int) $row->calls;

            if ($price === null && $unpricedUnits > 0) {
                $unpriced[] = [
                    'model' => $model,
                    'unit' => AiFeature::tryFrom((string) $row->feature)?->unit() ?? 'tokens',
                ];
            }
        }

        usort($unpriced, fn (array $a, array $b): int => $a['model'] <=> $b['model']);

        return new CreditBalance(
            account: $account,
            creditUsd: $grants['usd'],
            creditTokens: $grants['tokens'],
            spentUsd: round($spentUsd, 4),
            spentTokens: $spentTokens,
            limitedByUsd: $grants['limitedByUsd'],
            limitedByTokens: $grants['limitedByTokens'],
            paused: $setting?->paused_at !== null,
            since: $setting?->metering_started_at,
            calls: $calls,
            unpricedModels: $unpriced,
        );
    }

    /**
     * What the owner granted: the recharge totals, and which units carry a
     * limit (any non-zero row in that unit, D6).
     *
     * @return array{usd: float, tokens: int, limitedByUsd: bool, limitedByTokens: bool}
     */
    private function grants(ApiAccount $account): array
    {
        if (isset($this->grants[$account->value])) {
            return $this->grants[$account->value];
        }

        $row = ApiCreditTopup::query()
            ->where('account', $account->value)
            ->toBase()
            ->selectRaw('sum(amount_usd) as usd, sum(amount_tokens) as tokens, sum(case when amount_usd <> 0 then 1 else 0 end) as usd_rows, sum(case when amount_tokens <> 0 then 1 else 0 end) as token_rows')
            ->first();

        return $this->grants[$account->value] = [
            'usd' => round((float) ($row->usd ?? 0), 4),
            'tokens' => (int) ($row->tokens ?? 0),
            'limitedByUsd' => (int) ($row->usd_rows ?? 0) > 0,
            'limitedByTokens' => $account->tracksTokens() && (int) ($row->token_rows ?? 0) > 0,
        ];
    }

    /**
     * The account's usage rows since metering started (all of them before
     * the first recharge, for the console's "spent so far").
     *
     * @return Builder<AiUsage>
     */
    private function usage(ApiAccount $account): Builder
    {
        $since = $this->keyring->setting($account)?->metering_started_at;

        return AiUsage::query()
            ->where(function (Builder $query) use ($account): void {
                // `_` is a LIKE wildcard, so `qwen_%` also matches the
                // literal `qwen_…` providers; no escape needed on either
                // SQLite or MySQL.
                $query->where('provider', $account->value)
                    ->orWhere('provider', 'like', $account->value.'_%');

                if ($account === ApiAccount::Qwen) {
                    $query->orWhere('provider', 'dashscope');
                }
            })
            ->when($since !== null, fn (Builder $query) => $query->where('occurred_at', '>=', $since));
    }
}

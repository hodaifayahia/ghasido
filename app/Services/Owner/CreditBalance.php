<?php

namespace App\Services\Owner;

use App\Enums\ApiAccount;
use Carbon\CarbonInterface;

/**
 * One paid API account's credit at a moment (spec 0007, D4–D6): what the
 * owner granted, what the app spent since metering started, and whether
 * the account may still be called.
 */
final readonly class CreditBalance
{
    /** Below this share of the credit left, the account shows as low. */
    public const float LOW_SHARE = 0.10;

    /**
     * @param  list<array{model: string, unit: string}>  $unpricedModels  models with billable usage but no price, and the unit they count in
     */
    public function __construct(
        public ApiAccount $account,
        public float $creditUsd,
        public int $creditTokens,
        public float $spentUsd,
        public int $spentTokens,
        public bool $limitedByUsd,
        public bool $limitedByTokens,
        public bool $paused,
        public ?CarbonInterface $since,
        public int $calls,
        public array $unpricedModels,
    ) {}

    public function remainingUsd(): ?float
    {
        return $this->limitedByUsd ? round($this->creditUsd - $this->spentUsd, 4) : null;
    }

    public function remainingTokens(): ?int
    {
        return $this->limitedByTokens ? $this->creditTokens - $this->spentTokens : null;
    }

    public function isLimited(): bool
    {
        return $this->limitedByUsd || $this->limitedByTokens;
    }

    public function exhausted(): bool
    {
        return ($this->limitedByUsd && $this->creditUsd - $this->spentUsd <= 0)
            || ($this->limitedByTokens && $this->creditTokens - $this->spentTokens <= 0);
    }

    /**
     * Whether the app must stop calling this account now.
     */
    public function blocked(): bool
    {
        return $this->paused || $this->exhausted();
    }

    public function low(): bool
    {
        $usdLow = $this->limitedByUsd && $this->creditUsd > 0
            && ($this->creditUsd - $this->spentUsd) / $this->creditUsd < self::LOW_SHARE;
        $tokensLow = $this->limitedByTokens && $this->creditTokens > 0
            && ($this->creditTokens - $this->spentTokens) / $this->creditTokens < self::LOW_SHARE;

        return $usdLow || $tokensLow;
    }

    /**
     * `paused`, `exhausted`, `unlimited` (no recharge yet), `low` or `active`.
     */
    public function state(): string
    {
        return match (true) {
            $this->paused => 'paused',
            $this->exhausted() => 'exhausted',
            ! $this->isLimited() => 'unlimited',
            $this->low() => 'low',
            default => 'active',
        };
    }
}

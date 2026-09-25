<?php

namespace App\Services\Owner;

use App\Enums\ApiAccount;
use App\Enums\CreditMeter;
use Carbon\CarbonInterface;

/**
 * One paid API account's credit at a moment (spec 0007, D4–D6, D10).
 *
 * Two ways to count, chosen by what the owner recharged:
 *
 * - **Pack** (`units`): a recharge gave provider units ("$200 = 250,000
 *   tokens"). The units are the limit; the dollars are what the Super Admin
 *   paid and sees, going down in step with the units used. With several
 *   meters (Deepgram minutes and characters) the one nearest empty counts.
 * - **Dollars** (`dollars`): recharges gave dollars only; usage is costed
 *   at the owner's prices and taken off them.
 *
 * `costUsd` is always the real cost at the owner's prices: the owner's own
 * figure, never shown to the Super Admin.
 */
final readonly class CreditBalance
{
    /** Below this share of the credit left, the account shows as low and
     * the "running low" email goes out (spec 0007, D12). */
    public const float LOW_SHARE = 0.20;

    /**
     * @param  array<string, array{granted: int, used: int, limited: bool}>  $meters  keyed by CreditMeter value, one per meter of the account
     * @param  list<array{model: string, unit: string}>  $unpricedModels  models with billable usage but no price, and the unit they count in
     */
    public function __construct(
        public ApiAccount $account,
        public float $creditUsd,
        public bool $hasUsdGrant,
        public float $costUsd,
        public array $meters,
        public bool $paused,
        public ?CarbonInterface $since,
        public int $calls,
        public array $unpricedModels,
    ) {}

    /** `units` (a pack), `dollars`, or `none` (no recharge yet). */
    public function mode(): string
    {
        foreach ($this->meters as $meter) {
            if ($meter['limited']) {
                return 'units';
            }
        }

        return $this->hasUsdGrant ? 'dollars' : 'none';
    }

    public function isLimited(): bool
    {
        return $this->mode() !== 'none';
    }

    public function meterLeft(CreditMeter $meter): ?int
    {
        $row = $this->meters[$meter->value] ?? null;

        return $row !== null && $row['limited'] ? $row['granted'] - $row['used'] : null;
    }

    /**
     * The share of the credit left, 0–1; null without a limit.
     */
    public function shareLeft(): ?float
    {
        return match ($this->mode()) {
            'units' => $this->unitShareLeft(),
            'dollars' => $this->creditUsd > 0 ? max(0.0, min(1.0, ($this->creditUsd - $this->costUsd) / $this->creditUsd)) : 0.0,
            default => null,
        };
    }

    /**
     * The dollars left as the Super Admin sees them: a pack's dollars in
     * step with its units, or the dollar credit minus the cost. Never below
     * zero; null without a limit.
     */
    public function remainingUsd(): ?float
    {
        return match ($this->mode()) {
            'units' => round($this->creditUsd * ($this->unitShareLeft() ?? 0.0), 2),
            'dollars' => round(max(0.0, $this->creditUsd - $this->costUsd), 4),
            default => null,
        };
    }

    /** The dollars used as the Super Admin sees them. */
    public function usedUsd(): float
    {
        return match ($this->mode()) {
            'units', 'dollars' => round($this->creditUsd - ($this->remainingUsd() ?? 0.0), 4),
            default => $this->costUsd,
        };
    }

    public function exhausted(): bool
    {
        return match ($this->mode()) {
            'units' => $this->anyMeterEmpty(),
            'dollars' => $this->creditUsd - $this->costUsd <= 0,
            default => false,
        };
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
        $share = $this->shareLeft();

        return $share !== null && $share < self::LOW_SHARE;
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

    /**
     * The smallest share left across the limited meters.
     */
    private function unitShareLeft(): ?float
    {
        $share = null;

        foreach ($this->meters as $meter) {
            if (! $meter['limited']) {
                continue;
            }

            $left = $meter['granted'] > 0 ? ($meter['granted'] - $meter['used']) / $meter['granted'] : 0.0;
            $share = min($share ?? 1.0, max(0.0, min(1.0, $left)));
        }

        return $share;
    }

    private function anyMeterEmpty(): bool
    {
        foreach ($this->meters as $meter) {
            if ($meter['limited'] && $meter['granted'] - $meter['used'] <= 0) {
                return true;
            }
        }

        return false;
    }
}

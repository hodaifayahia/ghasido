<?php

namespace App\Services\Owner;

use App\Enums\ApiAccount;

/**
 * Each paid account's credit as the Super Admin sees it (spec 0007, D11):
 * the dollars she was credited and what is left of them, the units left of
 * a pack, and the state. Never the owner's real cost, prices or keys.
 */
final class CreditSummary
{
    public function __construct(private readonly ApiCredit $credit) {}

    /**
     * @return list<array{account: string, service: string, provider: string, state: string, mode: string, creditUsd: float, remainingUsd: float|null, usedUsd: float|null, shareLeft: float|null, meters: list<array{meter: string, label: string, granted: int, left: int}>}>
     */
    public function forSuperAdmin(): array
    {
        return array_map(fn (ApiAccount $account): array => $this->present($account), ApiAccount::cases());
    }

    /**
     * @return array{account: string, service: string, provider: string, state: string, mode: string, creditUsd: float, remainingUsd: float|null, usedUsd: float|null, shareLeft: float|null, meters: list<array{meter: string, label: string, granted: int, left: int}>}
     */
    public function present(ApiAccount $account): array
    {
        $balance = $this->credit->balance($account);
        $mode = $balance->mode();
        $meters = [];

        foreach ($account->meters() as $meter) {
            $row = $balance->meters[$meter->value] ?? null;

            if ($row === null || ! $row['limited']) {
                continue;
            }

            $meters[] = [
                'meter' => $meter->value,
                'label' => $meter->label(),
                'granted' => $row['granted'],
                'left' => max(0, $row['granted'] - $row['used']),
            ];
        }

        return [
            'account' => $account->value,
            'service' => $account->serviceLabel(),
            'provider' => $account->label(),
            'state' => $balance->state(),
            'mode' => $mode,
            'creditUsd' => $balance->creditUsd,
            'remainingUsd' => $balance->remainingUsd(),
            // Without a limit the only dollar figure is the owner's cost,
            // which is not hers to see.
            'usedUsd' => $mode === 'none' ? null : $balance->usedUsd(),
            'shareLeft' => $balance->shareLeft(),
            'meters' => $meters,
        ];
    }
}

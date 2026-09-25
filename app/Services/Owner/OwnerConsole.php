<?php

namespace App\Services\Owner;

use App\Enums\ApiAccount;
use App\Enums\CreditMeter;
use App\Models\AiModelPrice;
use App\Models\ApiCreditTopup;
use App\Services\Ai\AiModelSettings;

/**
 * Everything the owner console page shows (spec 0007): per paid account
 * its credit, spend, key source (masked, never the key), last connection
 * checks and recharge history; then the price table.
 */
final class OwnerConsole
{
    /** How many recharges each account lists. */
    public const int HISTORY = 12;

    private const array CHECK_LABELS = [
        'ai' => 'Text model',
        'fast' => 'Fast text model',
        'image' => 'Images',
        'tts' => 'Speech (text to speech)',
        'stt' => 'Transcription (speech to text)',
    ];

    public function __construct(
        private readonly ApiCredit $credit,
        private readonly ApiKeyring $keyring,
        private readonly AiModelSettings $models,
        private readonly DeepgramBalance $deepgram,
        private readonly CreditSummary $summary,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $checks = $this->models->checks();

        return [
            'accounts' => array_map(
                fn (ApiAccount $account): array => $this->account($account, $checks),
                ApiAccount::cases(),
            ),
            'prices' => AiModelPrice::query()->orderBy('model')->get()
                ->map(fn (AiModelPrice $price): array => [
                    'model' => $price->model,
                    'unit' => $price->unit,
                    'input' => (float) $price->input_per_million,
                    'output' => (float) $price->output_per_million,
                ])
                ->values()
                ->all(),
            'units' => AiModelPrice::UNITS,
            'deepgramBalance' => $this->deepgram->last(),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>|null>  $checks
     * @return array<string, mixed>
     */
    private function account(ApiAccount $account, array $checks): array
    {
        $balance = $this->credit->balance($account);
        $setting = $this->keyring->setting($account);

        return [
            'account' => $account->value,
            'label' => $account->label(),
            'vendor' => $account->vendor(),
            'usedFor' => $account->usedFor(),
            'state' => $balance->state(),
            'paused' => $balance->paused,
            // What the Super Admin sees (D10, D11): the pack's dollars in
            // step with its units, or the dollar credit.
            'mode' => $balance->mode(),
            'client' => $this->summary->present($account),
            // The owner's own figure: usage at the owner's prices.
            'costUsd' => $balance->costUsd,
            'meters' => array_map(
                fn (CreditMeter $meter): array => [
                    'meter' => $meter->value,
                    'label' => $meter->label(),
                    'covers' => $meter->covers(),
                    'field' => $meter->inputField(),
                    'scale' => $meter->inputScale(),
                    'limited' => $balance->meters[$meter->value]['limited'] ?? false,
                    'granted' => $balance->meters[$meter->value]['granted'] ?? 0,
                    'used' => $balance->meters[$meter->value]['used'] ?? 0,
                ],
                $account->meters(),
            ),
            'since' => $balance->since?->toIso8601String(),
            'calls' => $balance->calls,
            'unpricedModels' => $balance->unpricedModels,
            'byFeature' => array_slice($this->credit->spendByFeature($account), 0, 8),
            'key' => [
                'source' => $this->keyring->source($account),
                'masked' => $this->keyring->masked($account),
                'updatedAt' => $setting?->key_updated_at?->toIso8601String(),
            ],
            'checks' => array_map(
                fn (string $capability): array => [
                    'capability' => $capability,
                    'label' => self::CHECK_LABELS[$capability] ?? $capability,
                    'state' => $checks[$capability] ?? null,
                ],
                $account->checks(),
            ),
            'topups' => ApiCreditTopup::query()
                ->with('owner:id,name')
                ->where('account', $account->value)
                ->latest('id')
                ->limit(self::HISTORY)
                ->get()
                ->map(fn (ApiCreditTopup $topup): array => [
                    'id' => $topup->id,
                    'usd' => (float) $topup->amount_usd,
                    'tokens' => $topup->amount_tokens,
                    'characters' => $topup->amount_characters,
                    'seconds' => $topup->amount_seconds,
                    'note' => $topup->note,
                    'createdAt' => $topup->created_at?->toIso8601String(),
                    'by' => $topup->owner?->name,
                ])
                ->values()
                ->all(),
        ];
    }
}

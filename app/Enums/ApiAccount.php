<?php

namespace App\Enums;

/**
 * The paid API accounts the platform owner funds and controls from the
 * owner console (spec 0007): their keys, their credit and their prices.
 *
 * An account covers every `ai_usages.provider` value that bills to it, so
 * spend is summed from the one ledger the rest of the app already writes
 * (API-03, AIL-04).
 */
enum ApiAccount: string
{
    case Qwen = 'qwen';
    case Deepgram = 'deepgram';

    public function label(): string
    {
        return match ($this) {
            self::Qwen => 'Qwen',
            self::Deepgram => 'Deepgram',
        };
    }

    public function vendor(): string
    {
        return match ($this) {
            self::Qwen => 'Alibaba Model Studio',
            self::Deepgram => 'Deepgram',
        };
    }

    /**
     * The account as the Super Admin knows it: the service, not the vendor
     * contract behind it.
     */
    public function serviceLabel(): string
    {
        return match ($this) {
            self::Qwen => 'AI text & images',
            self::Deepgram => 'Speech & listening',
        };
    }

    /**
     * What the app uses this account for, in plain words for the console.
     */
    public function usedFor(): string
    {
        return match ($this) {
            self::Qwen => 'Text AI: role-play replies, feedback and evaluations, lesson and test drafts, coaching, and lesson images.',
            self::Deepgram => 'Speech: lesson audio, transcription of spoken answers, pronunciation checks and live voice calls.',
        };
    }

    /**
     * The provider units a recharge can buy (spec 0007, D10): Qwen is sold
     * as a token plan; Deepgram bills speech by the character and listening
     * (transcription, pronunciation, voice calls) by the second.
     *
     * @return list<CreditMeter>
     */
    public function meters(): array
    {
        return match ($this) {
            self::Qwen => [CreditMeter::Tokens],
            self::Deepgram => [CreditMeter::Seconds, CreditMeter::Characters],
        };
    }

    /**
     * The "Test" buttons (RunProviderCheck capabilities) that exercise this
     * account.
     *
     * @return list<string>
     */
    public function checks(): array
    {
        return match ($this) {
            // Text only: an image check generates (and bills) a picture.
            self::Qwen => ['ai'],
            self::Deepgram => ['tts', 'stt'],
        };
    }

    /**
     * Does an `ai_usages.provider` value (or a provider binding name) bill
     * to this account? The Qwen voice proxy writes `qwen_voice_proxy` and
     * the voice agent `deepgram_agent_*`, hence the prefix match.
     */
    public function matchesProvider(string $provider): bool
    {
        if ($provider === $this->value || str_starts_with($provider, $this->value.'_')) {
            return true;
        }

        return $this === self::Qwen && $provider === 'dashscope';
    }

    public static function forProvider(string $provider): ?self
    {
        foreach (self::cases() as $account) {
            if ($account->matchesProvider($provider)) {
                return $account;
            }
        }

        return null;
    }
}

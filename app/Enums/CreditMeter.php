<?php

namespace App\Enums;

/**
 * A unit a paid API account's credit can be counted in (spec 0007, D10):
 * the provider's own unit, so a recharge can say "$200 buys 250,000 Qwen
 * tokens" or "$50 buys 300 Deepgram minutes".
 *
 * The value is the unit `AiFeature::unit()` gives a usage row, so a row's
 * units count against the meter of the same name.
 */
enum CreditMeter: string
{
    case Tokens = 'tokens';
    case Characters = 'characters';
    case Seconds = 'seconds';

    /** The ledger column this meter's recharges are summed from. */
    public function column(): string
    {
        return match ($this) {
            self::Tokens => 'amount_tokens',
            self::Characters => 'amount_characters',
            self::Seconds => 'amount_seconds',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Tokens => 'Tokens',
            self::Characters => 'Speech characters',
            self::Seconds => 'Audio minutes',
        };
    }

    /**
     * What the owner types for this meter: seconds are entered and shown as
     * minutes, everything else as is.
     */
    public function inputField(): string
    {
        return match ($this) {
            self::Tokens => 'tokens',
            self::Characters => 'characters',
            self::Seconds => 'minutes',
        };
    }

    /** Stored units per entered unit (a minute is 60 seconds). */
    public function inputScale(): int
    {
        return $this === self::Seconds ? 60 : 1;
    }

    /** What the meter covers, for the console. */
    public function covers(): string
    {
        return match ($this) {
            self::Tokens => 'Every text call: role-play replies, feedback, evaluations, drafts and coaching.',
            self::Characters => 'Lesson audio and other text-to-speech.',
            self::Seconds => 'Transcription, pronunciation checks and live voice calls.',
        };
    }
}

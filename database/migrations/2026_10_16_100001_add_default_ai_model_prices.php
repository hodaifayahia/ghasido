<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Default prices for the models GHASIDO calls, so the owner's dollar credit
 * goes down as soon as they are used (API-03, AIL-04; spec 0007, D5, D9).
 * With no price an ai_usages row costs $0 and a dollar budget never moves.
 *
 * List prices on 2026-10-16, per million units: Claude per token
 * (input / output; cache reads and writes are derived in AiModelPrice),
 * Deepgram speech per character and listening per second (the per-minute
 * price / 60). A model the owner already priced is left untouched; the
 * owner edits all of these on the console (D8).
 */
return new class extends Migration
{
    /** @var list<array{0: string, 1: string, 2: float, 3: float}> */
    private const array PRICES = [
        // Anthropic Claude: $ per million tokens.
        ['claude-sonnet-5-5', 'tokens', 2.0, 10.0],
        ['claude-haiku-5-5', 'tokens', 0.10, 0.50],
        ['claude-opus-5-5', 'tokens', 4.0, 20.0],
        // Deepgram text-to-speech: Flux $0.045, Aura-2 $0.030 per 1,000 characters.
        ['flux-*', 'characters', 45.0, 0.0],
        ['aura-2-*', 'characters', 30.0, 0.0],
        // Deepgram listening: Nova-3 pre-recorded $0.0043/min, Flux streaming
        // $0.0065/min (English) and $0.0078/min (multilingual). The exact Flux
        // listening ids win over the `flux-*` speech pattern.
        ['nova-3*', 'seconds', 71.6667, 0.0],
        ['flux-general-en', 'seconds', 108.3333, 0.0],
        ['flux-general-multi', 'seconds', 130.0, 0.0],
        ['deepgram-flux-stream', 'seconds', 108.3333, 0.0],
        // Deepgram Voice Agent (live calls), Standard tier: $0.075/min.
        ['deepgram-voice-agent', 'seconds', 1250.0, 0.0],
    ];

    public function up(): void
    {
        $now = Date::now();

        foreach (self::PRICES as [$model, $unit, $input, $output]) {
            if (DB::table('ai_model_prices')->where('model', $model)->exists()) {
                continue;
            }

            DB::table('ai_model_prices')->insert([
                'model' => $model,
                'unit' => $unit,
                'input_per_million' => $input,
                'output_per_million' => $output,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Remove only the rows still at the price this migration wrote.
     */
    public function down(): void
    {
        foreach (self::PRICES as [$model, $unit, $input, $output]) {
            DB::table('ai_model_prices')
                ->where('model', $model)
                ->where('unit', $unit)
                ->where('input_per_million', $input)
                ->where('output_per_million', $output)
                ->delete();
        }
    }
};

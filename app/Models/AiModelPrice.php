<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The price of one AI model (API-03, AIL-04; spec 0005 §4.3), per million
 * units: tokens for text models, characters for speech, seconds of audio
 * for transcription and live voice calls, and images (spec 0007, D9).
 * Edited by the platform owner on the owner console (spec 0007, D8).
 *
 * A model id ending in `*` prices every model starting with the rest
 * (`aura-2-*`), so one row covers a provider's whole voice list. An exact
 * id always wins over a pattern; the longest pattern wins otherwise.
 *
 * @property int $id
 * @property string $model
 * @property string $unit
 * @property string $input_per_million
 * @property string $output_per_million
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['model', 'unit', 'input_per_million', 'output_per_million'])]
class AiModelPrice extends Model
{
    /** @var list<string> */
    public const UNITS = ['tokens', 'characters', 'seconds', 'images'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input_per_million' => 'decimal:4',
            'output_per_million' => 'decimal:4',
        ];
    }

    /**
     * The cost of one call at this price.
     */
    public function costOf(int $inputUnits, int $outputUnits): float
    {
        return round(
            ($inputUnits * (float) $this->input_per_million + $outputUnits * (float) $this->output_per_million) / 1_000_000,
            6,
        );
    }

    /**
     * Every price keyed by model id (patterns included), for pricing many
     * rows at once with lookup().
     *
     * @return array<string, self>
     */
    public static function byModel(): array
    {
        return self::query()->get()->keyBy('model')->all();
    }

    /**
     * The price for one model id from a byModel() table: the exact row,
     * else the longest matching `prefix*` row.
     *
     * @param  array<string, self>  $prices
     */
    public static function lookup(array $prices, string $model): ?self
    {
        if (isset($prices[$model])) {
            return $prices[$model];
        }

        // The id as the owner typed it may differ from the one the provider
        // reported only in case or in a vendor prefix (`Qwen3.8-Flash`,
        // `qwen/qwen3.8-flash`): try each spelling of the id, exact rows
        // before patterns, so such usage is not left at $0 (API-03).
        $candidates = array_values(array_unique(array_filter([
            $model,
            strtolower($model),
            self::withoutVendor($model),
            strtolower(self::withoutVendor($model)),
        ], fn (string $id): bool => $id !== '')));

        $exact = [];

        foreach ($prices as $id => $price) {
            $id = strtolower(self::withoutVendor((string) $id));

            if (! str_ends_with($id, '*')) {
                $exact[$id] ??= $price;
            }
        }

        foreach ($candidates as $candidate) {
            if (isset($exact[strtolower($candidate)])) {
                return $exact[strtolower($candidate)];
            }
        }

        $best = null;
        $bestLength = -1;

        foreach ($prices as $pattern => $price) {
            $pattern = strtolower(self::withoutVendor((string) $pattern));

            if (! str_ends_with($pattern, '*')) {
                continue;
            }

            $prefix = substr($pattern, 0, -1);

            foreach ($candidates as $candidate) {
                if (str_starts_with(strtolower($candidate), $prefix) && strlen($prefix) > $bestLength) {
                    $best = $price;
                    $bestLength = strlen($prefix);
                }
            }
        }

        return $best;
    }

    /**
     * A model id without a leading `vendor/` or `models/` path.
     */
    private static function withoutVendor(string $model): string
    {
        $slash = strrpos($model, '/');

        return $slash === false ? $model : substr($model, $slash + 1);
    }

    /**
     * The price for one model id, straight from the table.
     */
    public static function for(string $model): ?self
    {
        return self::lookup(self::byModel(), $model);
    }
}

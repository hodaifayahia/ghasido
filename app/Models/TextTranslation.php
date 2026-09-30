<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The meaning of one distinct English text in one helper language
 * (CTRL-01..03; client decisions 2026-09-26: Show Meaning on every English
 * text a learner reads, and 2026-09-30: Arabic, French and any language the
 * Super Admin adds).
 *
 * Keyed by a hash of the normalised text and the language, so "Check-in
 * basics" on the course card, in the lesson header and on the progress page
 * is translated once per language. `source` is `ai` until the Super Admin
 * corrects it (`manual`).
 *
 * @property int $id
 * @property string $hash
 * @property string $locale the helper language (HelperLanguages)
 * @property string $source_text
 * @property string|null $translation
 * @property GenerationStatus $status
 * @property string $source
 * @property string|null $failed_reason
 * @property int|null $requested_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['hash', 'locale', 'source_text', 'translation', 'status', 'source', 'failed_reason', 'requested_by', 'updated_by'])]
class TextTranslation extends Model
{
    public const int MAX_LENGTH = 1500;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => GenerationStatus::class];
    }

    /** Collapse whitespace so the same sentence always has one row. */
    public static function normalise(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    public static function hashOf(string $text): string
    {
        return hash('sha256', Str::lower(self::normalise($text)));
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

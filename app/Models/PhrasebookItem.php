<?php

namespace App\Models;

use App\Policies\PhrasebookItemPolicy;
use Database\Factories\PhrasebookItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry in an employee's phrasebook (PHRASE-01..05, DATA-09,
 * spec 0003 B.6).
 *
 * Either a saved lexicon item (`lexicon_item_id`) or a custom phrase
 * (`custom_text`, `custom_arabic`, `custom_hash`). The hash makes a custom
 * save idempotent: the same sentence saved from two lessons is one row.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $lexicon_item_id
 * @property string|null $custom_text
 * @property string|null $custom_arabic
 * @property string|null $custom_hash
 * @property int|null $source_lesson_id
 * @property Carbon $saved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'lexicon_item_id',
    'custom_text',
    'custom_arabic',
    'custom_hash',
    'source_lesson_id',
    'saved_at',
])]
#[UsePolicy(PhrasebookItemPolicy::class)]
class PhrasebookItem extends Model
{
    /** @use HasFactory<PhrasebookItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'saved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<LexiconItem, $this> */
    public function lexiconItem(): BelongsTo
    {
        return $this->belongsTo(LexiconItem::class);
    }

    /** @return BelongsTo<Lesson, $this> */
    public function sourceLesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'source_lesson_id');
    }

    public function isCustom(): bool
    {
        return $this->lexicon_item_id === null;
    }

    /**
     * The hash a custom phrase is deduplicated on: sha256 of the text with
     * whitespace collapsed, trimmed and lower-cased, so "Towel " and "towel"
     * are one phrase. Mirrors AudioClip::hashFor() (spec 0003 B.4).
     */
    public static function hashFor(string $text): string
    {
        $normalised = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));

        return hash('sha256', $normalised);
    }
}

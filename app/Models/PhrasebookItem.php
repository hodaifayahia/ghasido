<?php

namespace App\Models;

use App\Policies\PhrasebookItemPolicy;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\PhrasebookItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

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
 * @property int $review_box
 * @property int $review_count
 * @property int $lapse_count
 * @property Carbon|null $last_reviewed_at
 * @property Carbon|null $due_at
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
    'review_box',
    'review_count',
    'lapse_count',
    'last_reviewed_at',
    'due_at',
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
            'review_box' => 'integer',
            'review_count' => 'integer',
            'lapse_count' => 'integer',
            'last_reviewed_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    /**
     * Days until a card in each Leitner box comes back (spec 0005 §3.4):
     * box 0 is "new or missed", box 5 is "known".
     *
     * @var list<int>
     */
    public const REVIEW_INTERVALS = [0, 1, 3, 7, 14, 30];

    public const MAX_BOX = 5;

    /**
     * Due for review: never reviewed, or its date has come.
     *
     * @param  Builder<PhrasebookItem>  $query
     */
    public function scopeDueForReview(Builder $query, ?CarbonInterface $at = null): void
    {
        $query->where(fn (Builder $due) => $due
            ->whereNull('due_at')
            ->orWhere('due_at', '<=', $at ?? Date::now()));
    }

    /**
     * Record one review. Knowing it moves the card up a box and pushes it
     * further out; missing it sends it back to box 0, due again today, and
     * counts a lapse (the "needs practice" pile).
     */
    public function recordReview(bool $knewIt, ?CarbonInterface $at = null): void
    {
        $at = CarbonImmutable::instance($at ?? Date::now());
        $box = $knewIt ? min(self::MAX_BOX, $this->review_box + 1) : 0;

        $this->forceFill([
            'review_box' => $box,
            'review_count' => $this->review_count + 1,
            'lapse_count' => $this->lapse_count + ($knewIt ? 0 : 1),
            'last_reviewed_at' => $at,
            'due_at' => $knewIt ? $at->startOfDay()->addDays(self::REVIEW_INTERVALS[$box]) : $at,
        ])->save();
    }

    /**
     * Missed at least once and not yet back past the first box.
     */
    public function needsPractice(): bool
    {
        return $this->lapse_count > 0 && $this->review_box <= 1;
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

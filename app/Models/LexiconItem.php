<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use App\Enums\LexiconKind;
use Database\Factories\LexiconItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A word or expression with everything Show Meaning reveals (CMS-06,
 * CTRL-01..03, GEN-01, GEN-03, spec 0003 B.5).
 *
 * The Arabic columns leave the server only behind a Show Meaning tap and
 * never on a test page (CTRL-02, CTRL-04): the shaper, not this model,
 * decides what is serialized, so nothing here is #[Hidden].
 *
 * @property int $id
 * @property LexiconKind $kind
 * @property string $english_text
 * @property string|null $ipa
 * @property string|null $part_of_speech
 * @property string|null $arabic_meaning
 * @property string|null $simple_explanation
 * @property string|null $hotel_example
 * @property string|null $hotel_example_arabic
 * @property int|null $image_media_id
 * @property string $source
 * @property array<string, mixed>|null $ai_draft
 * @property GenerationStatus|null $ai_status
 * @property bool $show_meaning_enabled
 * @property int|null $department_id
 * @property int|null $hotel_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'kind',
    'english_text',
    'ipa',
    'part_of_speech',
    'arabic_meaning',
    'simple_explanation',
    'hotel_example',
    'hotel_example_arabic',
    'image_media_id',
    'source',
    'ai_draft',
    'ai_status',
    'show_meaning_enabled',
    'department_id',
    'hotel_id',
    'created_by',
])]
class LexiconItem extends Model
{
    /** @use HasFactory<LexiconItemFactory> */
    use HasFactory;

    public const string SOURCE_MANUAL = 'manual';

    public const string SOURCE_AI = 'ai';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => LexiconKind::class,
            'ai_draft' => 'array',
            'ai_status' => GenerationStatus::class,
            'show_meaning_enabled' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------- relations

    /** @return BelongsTo<MediaAsset, $this> */
    public function image(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'image_media_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Hotel, $this> */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsToMany<Block, $this> */
    public function blocks(): BelongsToMany
    {
        return $this->belongsToMany(Block::class)->withPivot('position');
    }

    // ----------------------------------------------------------------- scopes

    /**
     * @param  Builder<LexiconItem>  $query
     */
    public function scopeOfKind(Builder $query, LexiconKind $kind): void
    {
        $query->where('kind', $kind);
    }

    // ---------------------------------------------------------------- reading

    public function isWord(): bool
    {
        return $this->kind === LexiconKind::Word;
    }

    public function isExpression(): bool
    {
        return $this->kind === LexiconKind::Expression;
    }

    /**
     * May Show Meaning reveal this item's Arabic (CTRL-03)?
     */
    public function allowsShowMeaning(): bool
    {
        return $this->show_meaning_enabled && $this->arabic_meaning !== null;
    }

    public function hasPendingDraft(): bool
    {
        return $this->ai_draft !== null && $this->ai_status === GenerationStatus::Done;
    }

    /**
     * The sentences this item plays: the word itself and its hotel example.
     * AudioLibrary resolves each to a stored clip (CTRL-05).
     *
     * @return list<string>
     */
    public function playableTexts(): array
    {
        return array_values(array_filter([
            $this->english_text,
            $this->hotel_example,
        ], fn (?string $text): bool => $text !== null && trim($text) !== ''));
    }
}

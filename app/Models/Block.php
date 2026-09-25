<?php

namespace App\Models;

use App\Enums\BlockType;
use Database\Factories\BlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * One block is one CMS unit and one employee step (LESSON-01, LESSON-02,
 * BLD-03, BLD-04, spec 0003 B.5, B.10).
 *
 * `settings` follows the per-type contract in spec 0003 B.10. Vocabulary and
 * expressions blocks draw their items from block_lexicon_item; practice
 * blocks own activities through activity_placements.
 *
 * @property int $id
 * @property int $lesson_id
 * @property BlockType $type
 * @property string|null $title
 * @property int $position
 * @property string $layout
 * @property array<string, mixed>|null $settings
 * @property bool $is_visible
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['lesson_id', 'type', 'title', 'position', 'layout', 'settings', 'is_visible'])]
class Block extends Model
{
    /** @use HasFactory<BlockFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BlockType::class,
            'position' => 'integer',
            'settings' => 'array',
            'is_visible' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------- relations

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * The words or expressions of a vocabulary / expressions block, in order.
     *
     * @return BelongsToMany<LexiconItem, $this>
     */
    public function lexiconItems(): BelongsToMany
    {
        return $this->belongsToMany(LexiconItem::class)
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /**
     * The activities of a practice block, in order (PRAC-05).
     *
     * @return MorphMany<ActivityPlacement, $this>
     */
    public function placements(): MorphMany
    {
        return $this->morphMany(ActivityPlacement::class, 'placeable')
            ->orderBy('position')
            ->orderBy('id');
    }

    /**
     * Reusable AI scenarios offered by this role-play step, in author order
     * (RP-14, TSTM-05).
     *
     * The block is the lesson step, so this relationship is the canonical
     * lesson association rather than an unqueryable JSON list.
     *
     * @return BelongsToMany<AiScenario, $this>
     */
    public function scenarios(): BelongsToMany
    {
        return $this->belongsToMany(AiScenario::class, 'block_ai_scenario')
            ->withPivot('position')
            ->orderByPivot('position')
            ->orderByPivot('id');
    }

    // ---------------------------------------------------------------- reading

    /**
     * The heading the step page prints, the admin's title winning over the
     * type's default.
     */
    public function heading(): string
    {
        return $this->title ?? $this->type->heading();
    }

    /**
     * One value out of `settings`, typed loosely on purpose: the contract per
     * type is in spec 0003 B.10 and the shaper validates the shape.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * The scenario ids an AI role-play block offers (spec 0003 B.10).
     *
     * @return list<int>
     */
    public function scenarioIds(): array
    {
        $ids = $this->relationLoaded('scenarios')
            ? $this->scenarios->pluck('id')->all()
            : $this->scenarios()->pluck('ai_scenarios.id')->all();

        if ($ids !== []) {
            return array_values(array_map('intval', $ids));
        }

        // Compatibility for blocks imported before RP-14's pivot existed.
        $ids = $this->setting('scenario_ids', []);

        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_map('intval', array_filter($ids, 'is_numeric')));
    }
}

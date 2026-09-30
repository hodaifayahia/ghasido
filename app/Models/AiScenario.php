<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\GenerationStatus;
use App\Enums\ScenarioDifficulty;
use App\Policies\AiScenarioPolicy;
use Database\Factories\AiScenarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * An AI role-play scenario (RP-01, RP-02, RP-04..06, AIE-04, spec 0003 B.5).
 *
 * The role-play prompt is assembled from these columns plus a fixed system
 * instruction; a raw prompt is never accepted from the client (RP-04).
 *
 * @property int $id
 * @property int $department_id
 * @property int|null $hotel_id
 * @property string $title
 * @property string $slug
 * @property string $description
 * @property ScenarioDifficulty $difficulty
 * @property string $situation
 * @property string $ai_role
 * @property string $employee_role
 * @property string $objective
 * @property list<string>|null $goals
 * @property list<string>|null $useful_phrases
 * @property list<array{key: string, label: string, weight: int}>|null $feedback_criteria
 * @property array<string, mixed>|null $settings
 * @property int $attempts_allowed
 * @property string $input_mode
 * @property int $min_turns
 * @property int $max_turns
 * @property int|null $thumbnail_media_id
 * @property string $icon
 * @property string|null $quote
 * @property string|null $tip
 * @property ContentStatus $status
 * @property array<string, mixed>|null $ai_draft
 * @property GenerationStatus|null $ai_status
 * @property int|null $created_by
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'department_id',
    'hotel_id',
    'title',
    'slug',
    'description',
    'difficulty',
    'situation',
    'ai_role',
    'employee_role',
    'objective',
    'goals',
    'useful_phrases',
    'feedback_criteria',
    'settings',
    'attempts_allowed',
    'input_mode',
    'min_turns',
    'max_turns',
    'thumbnail_media_id',
    'icon',
    'quote',
    'tip',
    'status',
    'created_by',
    'ai_draft',
    'ai_status',
])]
#[UsePolicy(AiScenarioPolicy::class)]
class AiScenario extends Model
{
    /** @use HasFactory<AiScenarioFactory> */
    use HasFactory;

    /**
     * The criteria every scenario scores against unless the admin sets its
     * own (spec 0003 B.5, G.6).
     *
     * @return list<array{key: string, label: string, weight: int}>
     */
    public static function defaultFeedbackCriteria(): array
    {
        return [
            ['key' => 'pronunciation', 'label' => 'Pronunciation', 'weight' => 1],
            ['key' => 'grammar', 'label' => 'Grammar', 'weight' => 1],
            ['key' => 'vocabulary', 'label' => 'Vocabulary', 'weight' => 1],
            ['key' => 'fluency', 'label' => 'Fluency', 'weight' => 1],
            ['key' => 'politeness', 'label' => 'Politeness', 'weight' => 1],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'difficulty' => ScenarioDifficulty::class,
            'goals' => 'array',
            'useful_phrases' => 'array',
            'feedback_criteria' => 'array',
            'settings' => 'array',
            'attempts_allowed' => 'integer',
            'min_turns' => 'integer',
            'max_turns' => 'integer',
            'status' => ContentStatus::class,
            'archived_at' => 'datetime',
            'ai_draft' => 'array',
            'ai_status' => GenerationStatus::class,
        ];
    }

    // ---------------------------------------------------------------- relations

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

    /** @return BelongsTo<MediaAsset, $this> */
    public function thumbnail(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'thumbnail_media_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Lesson steps that offer this reusable scenario (RP-14, TSTM-05).
     *
     * A scenario can be reused by multiple lessons without being copied.
     * The block relation keeps the exact step and display order.
     *
     * @return BelongsToMany<Block, $this>
     */
    public function blocks(): BelongsToMany
    {
        return $this->belongsToMany(Block::class, 'block_ai_scenario')
            ->withPivot('position')
            ->orderByPivot('position')
            ->orderByPivot('id');
    }

    // ----------------------------------------------------------------- scopes

    /**
     * @param  Builder<AiScenario>  $query
     */
    public function scopePublished(Builder $query): void
    {
        // A removed row is never live again, whatever its status (DATA-10).
        $query->where('status', ContentStatus::Published)->whereNull($query->qualifyColumn('archived_at'));
    }

    /**
     * Rows still in the admin library: not removed by "Delete" (CMS-01).
     * A removed row keeps its answers for reports (DATA-10).
     *
     * @param  Builder<AiScenario>  $query
     */
    public function scopeNotArchived(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('archived_at'));
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * What one learner may practise: published, their department, shared or
     * their hotel (ROLE-02, spec 0003 Part E).
     *
     * @param  Builder<AiScenario>  $query
     */
    public function scopeForLearner(Builder $query, User $user): void
    {
        $query
            ->published()
            ->where('department_id', $user->learningDepartmentId() ?? 0)
            ->where(function (Builder $inner) use ($user): void {
                $inner->whereNull('hotel_id');

                if ($user->hotel_id !== null) {
                    $inner->orWhere('hotel_id', $user->hotel_id);
                }
            });
    }

    // ---------------------------------------------------------------- reading

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published;
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->whereKey($this->id)->forLearner($user)->exists();
    }

    /**
     * The criteria to score against: the row's own, or the platform default.
     *
     * @return list<array{key: string, label: string, weight: int}>
     */
    public function criteria(): array
    {
        $criteria = $this->feedback_criteria;

        return $criteria === null || $criteria === [] ? self::defaultFeedbackCriteria() : $criteria;
    }

    /**
     * @return list<string>
     */
    public function criteriaKeys(): array
    {
        return array_map(static fn (array $criterion): string => $criterion['key'], $this->criteria());
    }

    public function acceptsVoice(): bool
    {
        return $this->input_mode === 'voice' || $this->input_mode === 'both';
    }

    public function acceptsText(): bool
    {
        return $this->input_mode === 'text' || $this->input_mode === 'both';
    }

    public function hasPendingDraft(): bool
    {
        return $this->ai_draft !== null && $this->ai_status === GenerationStatus::Done;
    }
}

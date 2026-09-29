<?php

namespace App\Models;

use App\Enums\Accent;
use App\Enums\ContentStatus;
use App\Enums\GenerationStatus;
use App\Policies\LessonPolicy;
use App\Services\Tts\TtsSettings;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One learning session made of blocks (LESSON-01..04, CMS-05, spec 0003 B.5).
 *
 * `course_id` and `hotel_id` are denormalised from the unit's course and
 * written by the saving hook below, never by hand: the learner scope and the
 * reports filter on them without a join and can never disagree with the
 * course.
 *
 * @property int $id
 * @property int $unit_id
 * @property int $course_id
 * @property int|null $hotel_id
 * @property string $title
 * @property string $slug
 * @property string|null $introduction
 * @property list<string>|null $objectives
 * @property int|null $cover_media_id
 * @property int|null $estimated_minutes
 * @property array<string, mixed>|null $completion_condition
 * @property int $position
 * @property ContentStatus $status
 * @property Carbon|null $published_at
 * @property GenerationStatus|null $ai_status
 * @property Accent|null $accent
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $visible_blocks_count
 */
#[Fillable([
    'unit_id',
    'title',
    'slug',
    'introduction',
    'objectives',
    'cover_media_id',
    'estimated_minutes',
    'completion_condition',
    'position',
    'status',
    'published_at',
    'accent',
])]
#[UsePolicy(LessonPolicy::class)]
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'objectives' => 'array',
            'completion_condition' => 'array',
            'estimated_minutes' => 'integer',
            'position' => 'integer',
            'status' => ContentStatus::class,
            'archived_at' => 'datetime',
            'published_at' => 'datetime',
            'ai_status' => GenerationStatus::class,
            'accent' => Accent::class,
        ];
    }

    /**
     * The accent this lesson is taught and judged in (spec 0006 §3): its
     * own, else the accent of the platform voice.
     */
    public function speakingAccent(): Accent
    {
        return $this->accent ?? app(TtsSettings::class)->defaultAccent();
    }

    protected static function booted(): void
    {
        static::saving(function (Lesson $lesson): void {
            $lesson->syncCourseFromUnit();
        });
    }

    /**
     * Copy course_id and hotel_id down from the unit's course.
     *
     * Runs on every save, so an admin moving a lesson to another unit, or a
     * seeder creating one, never has to remember the two derived columns.
     */
    public function syncCourseFromUnit(): void
    {
        $needsSync = $this->isDirty('unit_id')
            || ! isset($this->attributes['course_id']);

        if (! $needsSync) {
            return;
        }

        $course = Course::query()
            ->whereHas('units', fn (Builder $units) => $units->whereKey($this->unit_id))
            ->first();

        if ($course === null) {
            return;
        }

        $this->course_id = $course->id;
        $this->hotel_id = $course->hotel_id;
    }

    // ---------------------------------------------------------------- relations

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Hotel, $this> */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    /**
     * Every block, hidden ones included, in builder order.
     *
     * @return HasMany<Block, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class)->orderBy('position')->orderBy('id');
    }

    /**
     * The steps a learner walks through, in order (LESSON-01, LESSON-03).
     *
     * A relation rather than a filtered collection so it eager loads and
     * counts (`withCount('visibleBlocks')`) like any other.
     *
     * @return HasMany<Block, $this>
     */
    public function visibleBlocks(): HasMany
    {
        return $this->hasMany(Block::class)
            ->where('is_visible', true)
            ->orderBy('position')
            ->orderBy('id');
    }

    // ----------------------------------------------------------------- scopes

    /**
     * @param  Builder<Lesson>  $query
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
     * @param  Builder<Lesson>  $query
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
     * What one learner may open: published, and on a course that is
     * published, of their department, shared or their hotel (ROLE-02,
     * JOURNEY-03, spec 0003 Part E).
     *
     * This is the visibility half of LessonPolicy::view; the policy adds the
     * pre-test gate (JOURNEY-01).
     *
     * @param  Builder<Lesson>  $query
     */
    public function scopeForLearner(Builder $query, User $user): void
    {
        // The unit counts too: My Lessons hides a draft unit's lessons, so
        // the totals, "continue" and the direct URL must agree with it.
        $query
            ->published()
            ->whereHas('unit', fn (Builder $unit) => $unit->where('status', ContentStatus::Published))
            ->whereHas('course', fn (Builder $course) => $course->forLearner($user));
    }

    // ---------------------------------------------------------------- reading

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published;
    }

    public function isShared(): bool
    {
        return $this->hotel_id === null;
    }

    /**
     * Is this lesson visible to this learner (the scope, applied to one row)?
     */
    public function isVisibleTo(User $user): bool
    {
        return static::query()
            ->whereKey($this->id)
            ->forLearner($user)
            ->exists();
    }

    /**
     * This lesson's 1-based number within its course, in learner order
     * ("Lesson 3 / 8"). 0 when the lesson is not in the course's ordered list
     * (for example a draft when only published lessons count).
     */
    public function stepNumberIn(?Course $course = null, bool $publishedOnly = true): int
    {
        $course ??= $this->course()->firstOrFail();

        /** @var list<int> $ids */
        $ids = $course->orderedLessons($publishedOnly)->pluck('lessons.id')->all();

        $index = array_search($this->id, $ids, true);

        return $index === false ? 0 : $index + 1;
    }

    /**
     * How many lessons the course has in learner order ("Lesson 3 / 8").
     */
    public function totalStepsIn(?Course $course = null, bool $publishedOnly = true): int
    {
        $course ??= $this->course()->firstOrFail();

        return $course->lessonsTotal($publishedOnly);
    }

    /**
     * The lesson after this one in the course, or null on the last.
     */
    public function nextIn(?Course $course = null): ?Lesson
    {
        $course ??= $this->course()->firstOrFail();

        /** @var list<Lesson> $lessons */
        $lessons = $course->orderedLessons()->get()->all();

        foreach ($lessons as $index => $lesson) {
            if ($lesson->id === $this->id) {
                return $lessons[$index + 1] ?? null;
            }
        }

        return null;
    }
}

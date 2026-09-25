<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An ordered group of units and lessons for one department (ORG-04, CMS-04,
 * spec 0003 B.5).
 *
 * `hotel_id` null means shared across hotels, so there is no ScopedToHotel:
 * visibility is decided by scopeForLearner and the policies, not by a tenant
 * scope that would hide the shared catalogue.
 *
 * @property int $id
 * @property int $department_id
 * @property int|null $hotel_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string $tone
 * @property int $position
 * @property ContentStatus $status
 * @property Carbon|null $published_at
 * @property int|null $cover_media_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $lessons_count
 */
#[Fillable([
    'department_id',
    'hotel_id',
    'title',
    'slug',
    'description',
    'tone',
    'position',
    'status',
    'published_at',
    'cover_media_id',
    'created_by',
])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Lessons denormalise the course's hotel; moving a course between
        // hotels moves every lesson with it, so the learner scope on lessons
        // never disagrees with the course.
        static::updated(function (Course $course): void {
            if ($course->wasChanged('hotel_id')) {
                $course->lessons()->update(['hotel_id' => $course->hotel_id]);
            }
        });
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
    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Unit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    // ----------------------------------------------------------------- scopes

    /**
     * @param  Builder<Course>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ContentStatus::Published);
    }

    /**
     * Shared rows plus this hotel's own (ORG-04, CMS-04).
     *
     * @param  Builder<Course>  $query
     */
    public function scopeSharedOrFor(Builder $query, ?int $hotelId): void
    {
        $query->where(function (Builder $inner) use ($hotelId): void {
            $inner->whereNull('hotel_id');

            if ($hotelId !== null) {
                $inner->orWhere('hotel_id', $hotelId);
            }
        });
    }

    /**
     * What one learner may see: published, their department, shared or their
     * hotel (ROLE-02, JOURNEY-03, spec 0003 Part E).
     *
     * A user with no department sees nothing rather than everything.
     *
     * @param  Builder<Course>  $query
     */
    public function scopeForLearner(Builder $query, User $user): void
    {
        $query
            ->published()
            ->where('department_id', $user->learningDepartmentId() ?? 0)
            ->sharedOrFor($user->hotel_id);
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
     * The lessons of this course in learner order: unit position, then lesson
     * position. Every "Lesson n / N" and "next lesson" reads this.
     *
     * @return Builder<Lesson>
     */
    public function orderedLessons(bool $publishedOnly = true): Builder
    {
        $query = Lesson::query()
            ->select('lessons.*')
            ->join('units', 'units.id', '=', 'lessons.unit_id')
            ->where('lessons.course_id', $this->id)
            ->orderBy('units.position')
            ->orderBy('units.id')
            ->orderBy('lessons.position')
            ->orderBy('lessons.id');

        if ($publishedOnly) {
            $query
                ->where('lessons.status', ContentStatus::Published)
                ->where('units.status', ContentStatus::Published);
        }

        return $query;
    }

    /**
     * How many lessons this course has for a learner ("Lesson 1 / 8").
     */
    public function lessonsTotal(bool $publishedOnly = true): int
    {
        return $this->orderedLessons($publishedOnly)->count();
    }
}

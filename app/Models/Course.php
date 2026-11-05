<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\CourseHotelScope;
use App\Enums\CourseTrack;
use App\Enums\EnglishLevel;
use App\Enums\EssentialsIcon;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * English Essentials topics are courses too (client request 2026-10-18):
 * `track` = essentials, no department of their own, and an audience of
 * departments in `course_department` (none = every department). They reuse
 * units, lessons, blocks and the whole editor, but scopeForLearner never
 * returns them, so they never count toward the journey or the certificate.
 *
 * @property int $id
 * @property int|null $department_id null only for an English Essentials topic
 * @property int|null $hotel_id
 * @property CourseHotelScope $hotel_scope which hotels see a shared course (client request 2026-11-05)
 * @property EnglishLevel|null $level null = every level
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string $tone
 * @property CourseTrack $track
 * @property EssentialsIcon|null $icon
 * @property int $position
 * @property ContentStatus $status
 * @property Carbon|null $published_at
 * @property int|null $cover_media_id
 * @property int|null $created_by
 * @property Carbon|null $archived_at removed by "Delete", kept for reports (DATA-10)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $lessons_count
 */
#[Fillable([
    'level',
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
    'track',
    'icon',
    'hotel_scope',
])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    /** A new course is department training unless said otherwise. */
    protected $attributes = [
        'track' => 'department',
        'hotel_scope' => 'all',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'status' => ContentStatus::class,
            'level' => EnglishLevel::class,
            'track' => CourseTrack::class,
            'hotel_scope' => CourseHotelScope::class,
            'icon' => EssentialsIcon::class,
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
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

    /**
     * Who sees an English Essentials topic. No row = every department.
     *
     * @return BelongsToMany<Department, $this>
     */
    public function audienceDepartments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'course_department')->withTimestamps();
    }

    /**
     * The hotel group of a shared course: the only hotels that see it, or
     * the hotels that do not, per `hotel_scope` (client request 2026-11-05).
     *
     * @return BelongsToMany<Hotel, $this>
     */
    public function scopedHotels(): BelongsToMany
    {
        return $this->belongsToMany(Hotel::class, 'course_hotel')->withTimestamps();
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
        // A removed course is never live again, whatever its status (DATA-10).
        $query->where('status', ContentStatus::Published)->whereNull($query->qualifyColumn('archived_at'));
    }

    /**
     * Courses still in the admin library: not removed by "Delete" (CMS-01).
     *
     * @param  Builder<Course>  $query
     */
    public function scopeNotArchived(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('archived_at'));
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
     * The courses one hotel's learners may see: that hotel's own, and the
     * shared ones whose hotel group lets it in — every hotel, only a chosen
     * group, or every hotel except a chosen group (ORG-04, CMS-04; client
     * request 2026-11-05).
     *
     * @param  Builder<Course>  $query
     */
    public function scopeOpenToHotel(Builder $query, ?int $hotelId): void
    {
        $query->where(function (Builder $inner) use ($hotelId): void {
            $inner->where(function (Builder $shared) use ($hotelId): void {
                $shared->whereNull($shared->qualifyColumn('hotel_id'))
                    ->where(function (Builder $group) use ($hotelId): void {
                        $inGroup = fn (Builder $hotel) => $hotel->whereKey($hotelId ?? 0);

                        $group->where($group->qualifyColumn('hotel_scope'), CourseHotelScope::All)
                            ->orWhere(fn (Builder $only) => $only
                                ->where($only->qualifyColumn('hotel_scope'), CourseHotelScope::Only)
                                ->whereHas('scopedHotels', $inGroup))
                            ->orWhere(fn (Builder $except) => $except
                                ->where($except->qualifyColumn('hotel_scope'), CourseHotelScope::Except)
                                ->whereDoesntHave('scopedHotels', $inGroup));
                    });
            });

            if ($hotelId !== null) {
                $inner->orWhere($inner->qualifyColumn('hotel_id'), $hotelId);
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
            ->where('track', CourseTrack::Department)
            ->where('department_id', $user->learningDepartmentId() ?? 0)
            ->openToHotel($user->hotel_id)
            ->forLevel($user->english_level);
    }

    /**
     * @param  Builder<Course>  $query
     */
    public function scopeEssentials(Builder $query): void
    {
        $query->where($query->qualifyColumn('track'), CourseTrack::Essentials);
    }

    /**
     * The English Essentials topics one learner may open: published, shared
     * or their hotel's, and meant for every department or for theirs
     * (client request 2026-10-18; ROLE-02). Level does not narrow them:
     * they are the beginner basics any learner may need.
     *
     * @param  Builder<Course>  $query
     */
    public function scopeEssentialsFor(Builder $query, User $user): void
    {
        $departmentId = $user->learningDepartmentId() ?? 0;

        $query
            ->published()
            ->essentials()
            ->openToHotel($user->hotel_id)
            ->where(function (Builder $audience) use ($departmentId): void {
                $audience
                    ->whereDoesntHave('audienceDepartments')
                    ->orWhereHas('audienceDepartments', fn (Builder $department) => $department->whereKey($departmentId));
            });
    }

    /**
     * The learner's level, or courses for every level (null). A learner with
     * no level yet sees every level (client decision 2026-09-30).
     *
     * @param  Builder<Course>  $query
     */
    public function scopeForLevel(Builder $query, ?EnglishLevel $level): void
    {
        if ($level !== null) {
            $query->where(fn (Builder $inner) => $inner->whereNull('level')->orWhere('level', $level->value));
        }
    }

    // ---------------------------------------------------------------- reading

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isEssentials(): bool
    {
        return $this->track === CourseTrack::Essentials;
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

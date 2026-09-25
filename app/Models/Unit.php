<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A unit groups the lessons of a course (spec 0003 B.5).
 *
 * Moving a unit to another course carries its lessons' denormalised
 * `course_id` and `hotel_id` with it (see booted()).
 *
 * @property int $id
 * @property int $course_id
 * @property string $title
 * @property int $position
 * @property ContentStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['course_id', 'title', 'position', 'status'])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'status' => ContentStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (Unit $unit): void {
            if ($unit->wasChanged('course_id')) {
                $course = $unit->course()->firstOrFail();

                $unit->lessons()->update([
                    'course_id' => $course->id,
                    'hotel_id' => $course->hotel_id,
                ]);
            }
        });
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position')->orderBy('id');
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published;
    }
}

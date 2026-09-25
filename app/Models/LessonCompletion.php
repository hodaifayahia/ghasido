<?php

namespace App\Models;

use Database\Factories\LessonCompletionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One lesson an employee has finished (PROG-02, spec 0003 B.6).
 *
 * Written when the last visible block is completed, idempotently on
 * (user, lesson). No timestamps.
 *
 * @property int $id
 * @property int $user_id
 * @property int $lesson_id
 * @property Carbon $completed_at
 */
#[Fillable(['user_id', 'lesson_id', 'completed_at'])]
class LessonCompletion extends Model
{
    /** @use HasFactory<LessonCompletionFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}

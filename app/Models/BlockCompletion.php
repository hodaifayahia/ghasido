<?php

namespace App\Models;

use Database\Factories\BlockCompletionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One step an employee has finished (PROG-01, PROG-03, spec 0003 B.6).
 *
 * Written on every step completion, idempotently on (user, block), so a
 * retried request leaves one row (PROG-04). No timestamps: `completed_at` is
 * the only moment that matters.
 *
 * @property int $id
 * @property int $user_id
 * @property int $lesson_id
 * @property int $block_id
 * @property Carbon $completed_at
 */
#[Fillable(['user_id', 'lesson_id', 'block_id', 'completed_at'])]
class BlockCompletion extends Model
{
    /** @use HasFactory<BlockCompletionFactory> */
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

    /** @return BelongsTo<Block, $this> */
    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }
}

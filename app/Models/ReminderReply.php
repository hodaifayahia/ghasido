<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\ReminderReplyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A learner's answer to a message that allowed replies (client request
 * 2026-11-07): their text, as written, and an optional 1-5 rating.
 *
 * One per reminder row, so one per learner per message; the learner may
 * edit it, which marks it unread again for the team. `read_at` is when
 * someone on the team (the Super Admin or the hotel's manager) opened it.
 *
 * @property int $id
 * @property int $reminder_id
 * @property int $user_id
 * @property string|null $body
 * @property int|null $rating
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Reminder|null $reminder
 * @property-read User|null $user
 */
#[Fillable(['reminder_id', 'user_id', 'body', 'rating', 'read_at'])]
class ReminderReply extends Model
{
    /** @use HasFactory<ReminderReplyFactory> */
    use HasFactory;

    /** The longest reply a learner may write. */
    public const MAX_BODY = 2000;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'read_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Reminder, $this> */
    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The replies this admin may read: all of them for the Super Admin, the
     * replies of their own hotel's employees for a manager (REM-07, ROLE-02).
     *
     * @param  Builder<ReminderReply>  $query
     */
    public function scopeVisibleTo(Builder $query, User $actor): void
    {
        if ($actor->hasRole(Role::SuperAdmin->value)) {
            return;
        }

        $hotelId = $actor->hotel_id ?? 0;

        $query->whereHas('user', fn (Builder $user) => $user->where('users.hotel_id', $hotelId));
    }

    /**
     * @param  Builder<ReminderReply>  $query
     */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }
}

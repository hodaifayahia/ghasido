<?php

namespace App\Models;

use App\Enums\EnglishLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A learner asks to change level before their level's Post-test (client
 * request 2026-10-04); the Super Admin approves or refuses it.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $hotel_id
 * @property EnglishLevel|null $from_level
 * @property EnglishLevel $to_level
 * @property string $reason
 * @property string $status pending | approved | rejected
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_note
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Hotel|null $hotel
 */
#[Fillable(['user_id', 'hotel_id', 'from_level', 'to_level', 'reason', 'status', 'decided_by', 'decided_at', 'decision_note', 'read_at'])]
class LevelChangeRequest extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_level' => EnglishLevel::class,
            'to_level' => EnglishLevel::class,
            'decided_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Hotel, $this> */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class)->withoutGlobalScopes();
    }
}

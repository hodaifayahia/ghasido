<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A hotel request for a paid AI pool recharge (AIL-01, ROLE-02, ADM-03). */
#[Fillable([
    'hotel_id',
    'requested_by',
    'top_up_id',
    'month_start',
    'status',
    'read_at',
    'fulfilled_at',
])]
class HotelAiPointTopUpRequest extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'month_start' => 'date',
            'read_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Hotel, $this> */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<HotelAiPointTopUp, $this> */
    public function topUp(): BelongsTo
    {
        return $this->belongsTo(HotelAiPointTopUp::class, 'top_up_id');
    }

    public function markRead(): self
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }

        return $this;
    }
}

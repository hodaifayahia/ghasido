<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A paid, auditable addition to one hotel's AI pool for a calendar month (AIL-01, ADM-03). */
#[Fillable([
    'hotel_id',
    'month_start',
    'points',
    'amount_dzd',
    'payment_method_id',
    'payment_reference',
    'received_by',
    'received_at',
])]
class HotelAiPointTopUp extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'month_start' => 'date',
            'points' => 'integer',
            'amount_dzd' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Hotel, $this> */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /** @return BelongsTo<SubscriptionPaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPaymentMethod::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}

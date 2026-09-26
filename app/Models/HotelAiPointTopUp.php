<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A paid, auditable addition to one hotel's AI pool for a calendar month
 * (AIL-01, ADM-03), paid in DZD or, for international hotels, in USD.
 *
 * @property int $id
 * @property int $hotel_id
 * @property Carbon $month_start
 * @property int $points
 * @property string $currency
 * @property int $amount_dzd
 * @property float|null $amount_usd
 */
#[Fillable([
    'hotel_id',
    'month_start',
    'points',
    'amount_dzd',
    'currency',
    'amount_usd',
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
            'amount_usd' => 'float',
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

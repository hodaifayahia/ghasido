<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A customer's manual payment, sent from the checkout (client request
 * 2026-09-27): the method they paid with, the amount, the transaction
 * reference they typed and the receipt they uploaded. It pays for a hotel
 * (hotel_id) or an individual subscription (individual_subscription_id),
 * and its status follows that account's approval.
 *
 * The receipt sits on the private disk and is served only to whoever may
 * manage subscriptions (SEC-04, PRIV-04).
 *
 * @property int $id
 * @property string $public_id
 * @property int|null $hotel_id
 * @property int|null $individual_subscription_id
 * @property int|null $subscription_plan_id
 * @property string $plan_name
 * @property string $currency
 * @property float $amount
 * @property int|null $payment_method_id
 * @property string $payment_method_name
 * @property string|null $reference
 * @property string|null $proof_path
 * @property string|null $proof_name
 * @property string|null $proof_mime
 * @property int|null $proof_size
 * @property string $payer_name
 * @property string $payer_email
 * @property string|null $payer_phone
 * @property PaymentStatus $status
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property string|null $rejection_reason
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Hotel|null $hotel
 * @property-read IndividualSubscription|null $individualSubscription
 */
#[Fillable([
    'public_id',
    'hotel_id',
    'individual_subscription_id',
    'subscription_plan_id',
    'plan_name',
    'currency',
    'amount',
    'payment_method_id',
    'payment_method_name',
    'reference',
    'proof_path',
    'proof_name',
    'proof_mime',
    'proof_size',
    'payer_name',
    'payer_email',
    'payer_phone',
    'status',
    'reviewed_at',
    'reviewed_by',
    'rejection_reason',
    'read_at',
])]
class PaymentSubmission extends Model
{
    /** The private disk the receipts live on. */
    public const string PROOF_DISK = 'local';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'proof_size' => 'integer',
            'status' => PaymentStatus::class,
            'reviewed_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Hotel, $this> */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<IndividualSubscription, $this> */
    public function individualSubscription(): BelongsTo
    {
        return $this->belongsTo(IndividualSubscription::class);
    }

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /** @return BelongsTo<SubscriptionPaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPaymentMethod::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @param Builder<self> $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', PaymentStatus::Pending->value);
    }

    public function isForIndividual(): bool
    {
        return $this->individual_subscription_id !== null;
    }

    public function isImageProof(): bool
    {
        return $this->proof_mime !== null && str_starts_with($this->proof_mime, 'image/');
    }
}

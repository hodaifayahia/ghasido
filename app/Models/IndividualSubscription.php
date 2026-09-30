<?php

namespace App\Models;

use App\Enums\ApprovalState;
use Database\Factories\IndividualSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * An individual subscriber's own configuration: a learner with no hotel
 * (user request 2026-09-25). It stands in for the hotel's contract and plan:
 * the access window, whether AI and voice practice are included, their point
 * costs and an optional daily AI turn cap. The monthly AI points live on
 * users.ai_points_allocated, as for every employee.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property bool $ai_enabled
 * @property bool $voice_enabled
 * @property int|null $daily_ai_turns
 * @property int $ai_action_points
 * @property int $voice_points_per_10_minutes
 * @property int|null $price_dzd
 * @property float|null $price_usd
 * @property int|null $subscription_plan_id the plan bought online, if any
 * @property ApprovalState $approval_state
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property string|null $rejection_reason
 * @property string|null $payment_reference
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable([
    'user_id',
    'starts_on',
    'ends_on',
    'ai_enabled',
    'voice_enabled',
    'daily_ai_turns',
    'ai_action_points',
    'voice_points_per_10_minutes',
    'price_dzd',
    'price_usd',
    'subscription_plan_id',
    'approval_state',
    'approved_at',
    'approved_by',
    'rejection_reason',
    'payment_reference',
    'notes',
    'created_by',
])]
class IndividualSubscription extends Model
{
    /** @use HasFactory<IndividualSubscriptionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'ai_enabled' => 'boolean',
            'voice_enabled' => 'boolean',
            'daily_ai_turns' => 'integer',
            'ai_action_points' => 'integer',
            'voice_points_per_10_minutes' => 'integer',
            'price_dzd' => 'integer',
            'price_usd' => 'float',
            'approval_state' => ApprovalState::class,
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /** @return HasMany<PaymentSubmission, $this> */
    public function paymentSubmissions(): HasMany
    {
        return $this->hasMany(PaymentSubmission::class);
    }

    public function isPending(): bool
    {
        return $this->approval_state === ApprovalState::Pending;
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The departments this subscriber studies, main one first. One or more
     * (owner request 2026-09-25).
     *
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'individual_subscription_department')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('individual_subscription_department.position');
    }

    /**
     * The ids of those departments, main one first.
     *
     * @return list<int>
     */
    public function departmentIds(): array
    {
        return array_values($this->departments()->pluck('departments.id')->map(fn (mixed $id): int => (int) $id)->all());
    }

    /** Not started yet, or the last day has passed. */
    public function isOutsideWindow(): bool
    {
        $today = Date::today();

        return ($this->starts_on !== null && $this->starts_on->greaterThan($today))
            || ($this->ends_on !== null && $this->ends_on->lessThan($today));
    }

    /** `upcoming`, `active` or `ended`, for the list's status pill. */
    public function windowState(): string
    {
        $today = Date::today();

        if ($this->starts_on !== null && $this->starts_on->greaterThan($today)) {
            return 'upcoming';
        }

        if ($this->ends_on !== null && $this->ends_on->lessThan($today)) {
            return 'ended';
        }

        return 'active';
    }

    /** Whole days left in the window, or null when it has no end. */
    public function daysRemaining(): ?int
    {
        if ($this->ends_on === null) {
            return null;
        }

        return max(0, (int) Date::today()->diffInDays($this->ends_on, false));
    }
}

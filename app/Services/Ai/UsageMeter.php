<?php

namespace App\Services\Ai;

use App\Contracts\AiUsageInfo;
use App\Enums\AiFeature;
use App\Enums\Role;
use App\Models\AiUsage;
use App\Models\Hotel;
use App\Models\RoleplayAttempt;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;

/**
 * Meters every AI, TTS and STT call and enforces the daily limits
 * (AIL-01..AIL-04, API-03; spec 0003 Part C).
 *
 * Two rules the callers rely on:
 *
 * 1. record() is called by the JOB after the provider answered, once per
 *    real call, so ai_usages is the ledger the admin reads (API-03).
 * 2. assertWithinLimits() is called by the CONTROLLER before it dispatches,
 *    so a blocked employee gets the message immediately and no job ever
 *    spends budget past the limit (AIL-03).
 *
 * Limits count role-play guest turns (`roleplay_turn` rows written today,
 * app timezone) per employee and per hotel against config guesvia.ai.limits.
 * Every other feature is metered but not capped: an evaluation or a lexicon
 * draft is triggered by finishing work, not by chatting, and blocking it
 * would strand an attempt half-scored.
 */
final class UsageMeter
{
    /**
     * Write one ai_usages row. Nothing is aggregated or cached: the report
     * pages sum the rows (AIL-04).
     */
    public function record(?User $user, AiFeature $feature, AiUsageInfo $usage, float $costEstimate = 0, bool $chargePoints = true): AiUsage
    {
        $points = $user !== null && $chargePoints ? $this->aiActionCost($user) : 0;

        return DB::transaction(function () use ($user, $feature, $usage, $costEstimate, $points): AiUsage {
            if ($user !== null) {
                // Serialize usage writes per employee so two parallel AI replies
                // cannot silently spend the same remaining points (AIL-01).
                User::query()->lockForUpdate()->find($user->id);
            }

            return AiUsage::query()->create([
                'user_id' => $user?->id,
                'hotel_id' => $user?->hotel_id,
                'feature' => $feature,
                'provider' => $usage->provider,
                'model' => $usage->model,
                'prompt_tokens' => $usage->promptTokens,
                'completion_tokens' => $usage->completionTokens,
                'cost_estimate' => $costEstimate,
                'points_charged' => $points,
                'occurred_at' => Date::now(),
            ]);
        });
    }

    /**
     * @throws AiLimitReached when the employee or their hotel has spent today's turns
     */
    public function assertWithinLimits(User $user, AiFeature $feature): void
    {
        $this->assertPointsAvailable($user, $this->aiActionCost($user));

        $perEmployee = self::perEmployeeDailyTurns();
        $perHotel = self::perHotelDailyTurns();

        if ($this->turnsUsedTodayBy($user) >= $perEmployee) {
            throw AiLimitReached::forEmployee($feature, $perEmployee);
        }

        if ($user->hotel_id !== null && $this->turnsUsedTodayByHotel($user->hotel_id) >= $perHotel) {
            throw AiLimitReached::forHotel($feature, $perHotel);
        }
    }

    /**
     * Whether the employee may still start or continue a role-play, for the
     * shared `journey` flag the UI reads (AIL-03).
     */
    public function isWithinLimits(User $user, AiFeature $feature): bool
    {
        try {
            $this->assertWithinLimits($user, $feature);
        } catch (AiLimitReached) {
            return false;
        }

        return true;
    }

    /** Voice calls use a time rate instead of the per-action rate. */
    public function assertVoicePointsAvailable(User $user): void
    {
        if (! $user->hasRole(Role::Employee->value)) {
            return;
        }

        $hotel = $this->hotelFor($user);
        $plan = $hotel?->subscriptionPlan;

        if ($plan !== null) {
            $this->assertPointsAvailable($user, $plan->voice_points_per_10_minutes);
        }
    }

    /** A live voice call may continue while its elapsed ten-minute blocks are affordable. */
    public function canContinueVoice(RoleplayAttempt $attempt): bool
    {
        $user = $attempt->user;

        if ($user === null || $user->hotel_id === null || ! $user->hasRole(Role::Employee->value)) {
            return true;
        }

        if (! $this->isWithinDailyLimits($user)) {
            return false;
        }

        $plan = $this->hotelFor($user)?->subscriptionPlan;

        if ($plan === null) {
            return false;
        }

        $elapsedMilliseconds = max(0, (int) abs($attempt->started_at->diffInMilliseconds(Date::now())));
        $blocks = max(1, (int) ceil($elapsedMilliseconds / 600000));

        return $this->availablePoints($user) >= $blocks * $plan->voice_points_per_10_minutes;
    }

    /** Charge one completed live call in rounded-up ten-minute blocks. */
    public function chargeVoiceAttempt(RoleplayAttempt $attempt): void
    {
        if ($attempt->is_preview || $attempt->ai_points_charged > 0) {
            return;
        }

        $user = $attempt->user;
        $plan = $user?->hotel_id !== null ? $this->hotelFor($user)?->subscriptionPlan : null;

        if ($user === null || ! $user->hasRole(Role::Employee->value) || $plan === null || ($attempt->duration_ms ?? 0) <= 0) {
            return;
        }

        $blocks = max(1, (int) ceil($attempt->duration_ms / 600000));
        $attempt->forceFill(['ai_points_charged' => $blocks * $plan->voice_points_per_10_minutes])->save();
    }

    public function pointsUsedThisMonth(User $user): int
    {
        $from = Date::now()->startOfMonth();

        return (int) AiUsage::query()
            ->where('user_id', $user->id)
            ->where('occurred_at', '>=', $from)
            ->sum('points_charged') + (int) RoleplayAttempt::query()
            ->where('user_id', $user->id)
            ->where('started_at', '>=', $from)
            ->sum('ai_points_charged');
    }

    public function availablePoints(User $user): int
    {
        return $user->ai_points_allocated - $this->pointsUsedThisMonth($user);
    }

    public function aiActionCost(User $user): int
    {
        if (! $user->hasRole(Role::Employee->value)) {
            return 0;
        }

        return $this->hotelFor($user)?->subscriptionPlan?->ai_action_points ?? 50;
    }

    public function turnsUsedTodayBy(User $user): int
    {
        return $this->turnsToday()->where('user_id', $user->id)->count();
    }

    public function turnsUsedTodayByHotel(int $hotelId): int
    {
        return $this->turnsToday()->where('hotel_id', $hotelId)->count();
    }

    private function isWithinDailyLimits(User $user): bool
    {
        return $this->turnsUsedTodayBy($user) < self::perEmployeeDailyTurns()
            && ($user->hotel_id === null || $this->turnsUsedTodayByHotel($user->hotel_id) < self::perHotelDailyTurns());
    }

    private function assertPointsAvailable(User $user, int $required): void
    {
        if ($user->hasRole(Role::Employee->value) && $user->hotel_id !== null && $this->availablePoints($user) < $required) {
            throw AiLimitReached::forPoints($required, max(0, $this->availablePoints($user)));
        }
    }

    private function hotelFor(User $user): ?Hotel
    {
        return $user->hotel_id === null
            ? null
            : Hotel::query()->withoutGlobalScopes()->with('subscriptionPlan')->find($user->hotel_id);
    }

    public static function perEmployeeDailyTurns(): int
    {
        return (int) config('guesvia.ai.limits.per_employee_daily_turns', 60);
    }

    public static function perHotelDailyTurns(): int
    {
        return (int) config('guesvia.ai.limits.per_hotel_daily_turns', 2000);
    }

    /**
     * @return Builder<AiUsage>
     */
    private function turnsToday(): Builder
    {
        return AiUsage::query()
            ->forFeature(AiFeature::RoleplayTurn)
            ->where('occurred_at', '>=', Date::now()->startOfDay());
    }
}

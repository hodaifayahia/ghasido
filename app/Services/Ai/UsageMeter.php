<?php

namespace App\Services\Ai;

use App\Contracts\AiUsageInfo;
use App\Enums\AiFeature;
use App\Enums\ApiAccount;
use App\Enums\Role;
use App\Models\AiModelPrice;
use App\Models\AiUsage;
use App\Models\Hotel;
use App\Models\IndividualSubscription;
use App\Models\RoleplayAttempt;
use App\Models\User;
use App\Services\Owner\ApiCredit;
use App\Services\Owner\CreditAlerts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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
    /** The model id a live voice call's agent time is metered under. */
    public const string VOICE_AGENT_MODEL = 'deepgram-voice-agent';

    /**
     * Write one ai_usages row. Nothing is aggregated or cached: the report
     * pages sum the rows (AIL-04).
     */
    public function record(?User $user, AiFeature $feature, AiUsageInfo $usage, float $costEstimate = 0, bool $chargePoints = true, ?int $hotelId = null): AiUsage
    {
        $points = $user !== null && $chargePoints ? $this->aiActionCost($user) : 0;

        // Priced from the Super Admin's table when the caller has no figure
        // of its own (API-03; spec 0005 §4.3). Unpriced models stay at 0 and
        // the usage page says so.
        // A fake (no-network) call is free whatever model id it reports.
        if ($usage->provider === 'fake') {
            $costEstimate = 0.0;
        } elseif ($costEstimate <= 0) {
            $costEstimate = AiModelPrice::for($usage->model)
                ?->costOf($usage->promptTokens, $usage->completionTokens) ?? 0.0;
        }

        $row = DB::transaction(function () use ($user, $feature, $usage, $costEstimate, $points, $hotelId): AiUsage {
            if ($user !== null) {
                // Serialize usage writes per employee so two parallel AI replies
                // cannot silently spend the same remaining points (AIL-01).
                User::query()->lockForUpdate()->find($user->id);
            }

            return AiUsage::query()->create([
                'user_id' => $user?->id,
                // A system call made for a hotel (its dashboard briefing)
                // is billed to that hotel although nobody triggered it.
                'hotel_id' => $user->hotel_id ?? $hotelId,
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

        // Warn before the credit runs out (spec 0007, D12). An alert
        // problem must never break the metering itself.
        try {
            app(CreditAlerts::class)->afterUsage($usage->provider);
        } catch (Throwable $e) {
            Log::warning('Credit alert check failed', ['provider' => $usage->provider, 'error' => $e->getMessage()]);
        }

        return $row;
    }

    /**
     * @throws AiLimitReached when the employee or their hotel has spent today's turns
     */
    public function assertWithinLimits(User $user, AiFeature $feature): void
    {
        // The platform owner's Qwen credit comes first (spec 0007, D7a).
        $this->assertCredit($user, 'ai');

        // An individual subscriber's own plan may leave AI out (user request
        // 2026-09-25).
        $individual = $this->individualPlan($user);

        if ($individual !== null && ! $individual->ai_enabled) {
            throw AiLimitReached::forIndividualPlan($feature);
        }

        $this->assertPointsAvailable($user, $this->aiActionCost($user));

        $perEmployee = $this->dailyTurnsFor($user);
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

    /**
     * A live voice call needs the Deepgram account, and Qwen too when the
     * agent thinks through our Qwen proxy (spec 0007, D7a). Checked for an
     * admin's preview as well: it spends the same credit.
     *
     * @throws AiLimitReached
     */
    public function assertVoiceCredit(User $user, bool $usesQwen): void
    {
        $this->assertCredit($user, ...($usesQwen ? ['voice', 'ai'] : ['voice']));
    }

    /**
     * Meter one finished live voice call: the Deepgram agent bills the
     * call's length, so one row per call in seconds (spec 0007, D9). Never
     * the learner's points (those are charged per ten-minute block).
     */
    public function recordVoiceCall(RoleplayAttempt $attempt): void
    {
        $durationMs = $attempt->duration_ms ?? 0;

        if ($durationMs <= 0) {
            return;
        }

        $user = $attempt->user;

        $this->record($user, AiFeature::VoiceCall, self::audioUsage(
            ApiAccount::Deepgram->value,
            self::VOICE_AGENT_MODEL,
            $durationMs,
        ), chargePoints: false, hotelId: $user?->hotel_id);
    }

    /**
     * Usage for a call billed by audio length: the seconds go in the input
     * units, rounded up, so a `seconds` price costs them (spec 0007, D9).
     * Zero when the length is unknown.
     */
    public static function audioUsage(string $provider, string $model, ?int $durationMs): AiUsageInfo
    {
        $seconds = $durationMs !== null && $durationMs > 0 ? (int) ceil($durationMs / 1000) : 0;

        return new AiUsageInfo($seconds, 0, $model, $provider);
    }

    /** Voice calls use a time rate instead of the per-action rate. */
    public function assertVoicePointsAvailable(User $user): void
    {
        if (! $user->hasRole(Role::Employee->value)) {
            return;
        }

        $individual = $this->individualPlan($user);

        if ($individual !== null) {
            if (! $individual->ai_enabled || ! $individual->voice_enabled) {
                throw AiLimitReached::forIndividualPlan(AiFeature::RoleplayTurn);
            }

            $this->assertPointsAvailable($user, $individual->voice_points_per_10_minutes);

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
        $individual = $user !== null ? $this->individualPlan($user) : null;

        if ($user === null || ($user->hotel_id === null && $individual === null) || ! $user->hasRole(Role::Employee->value)) {
            return true;
        }

        if (! $this->isWithinDailyLimits($user)) {
            return false;
        }

        if ($individual !== null && (! $individual->ai_enabled || ! $individual->voice_enabled)) {
            return false;
        }

        $rate = $individual !== null
            ? $individual->voice_points_per_10_minutes
            : $this->hotelFor($user)?->subscriptionPlan?->voice_points_per_10_minutes;

        if ($rate === null) {
            return false;
        }

        $elapsedMilliseconds = max(0, (int) abs($attempt->started_at->diffInMilliseconds(Date::now())));
        $blocks = max(1, (int) ceil($elapsedMilliseconds / 600000));

        return $this->availablePoints($user) >= $blocks * $rate;
    }

    /** Charge one completed live call in rounded-up ten-minute blocks. */
    public function chargeVoiceAttempt(RoleplayAttempt $attempt): void
    {
        if ($attempt->is_preview || $attempt->ai_points_charged > 0) {
            return;
        }

        $user = $attempt->user;

        if ($user === null) {
            return;
        }

        $individual = $this->individualPlan($user);
        $rate = $individual !== null
            ? $individual->voice_points_per_10_minutes
            : ($user->hotel_id !== null ? $this->hotelFor($user)?->subscriptionPlan?->voice_points_per_10_minutes : null);

        if (! $user->hasRole(Role::Employee->value) || $rate === null || ($attempt->duration_ms ?? 0) <= 0) {
            return;
        }

        $blocks = max(1, (int) ceil($attempt->duration_ms / 600000));
        $attempt->forceFill(['ai_points_charged' => $blocks * $rate])->save();
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

        $individual = $this->individualPlan($user);

        if ($individual !== null) {
            return $individual->ai_action_points;
        }

        // `??` also covers a hotel without a plan: the access is isset-guarded.
        return $this->hotelFor($user)?->subscriptionPlan->ai_action_points ?? 50;
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
        return $this->turnsUsedTodayBy($user) < $this->dailyTurnsFor($user)
            && ($user->hotel_id === null || $this->turnsUsedTodayByHotel($user->hotel_id) < self::perHotelDailyTurns());
    }

    /**
     * @throws AiLimitReached
     */
    private function assertCredit(User $user, string ...$capabilities): void
    {
        try {
            app(ApiCredit::class)->assertCapabilities(...$capabilities);
        } catch (AiLimitReached $exception) {
            // A learner is told AI practice is paused, nothing about credit;
            // the Super Admin sees which account ran out.
            throw $user->hasRole(Role::SuperAdmin->value) ? $exception : AiLimitReached::forLearnerCredit();
        }
    }

    private function assertPointsAvailable(User $user, int $required): void
    {
        $metered = $user->hotel_id !== null || $this->individualPlan($user) !== null;

        if ($user->hasRole(Role::Employee->value) && $metered && $this->availablePoints($user) < $required) {
            throw AiLimitReached::forPoints($required, max(0, $this->availablePoints($user)));
        }
    }

    /**
     * An individual subscriber's own plan, or null for everyone else (user
     * request 2026-09-25).
     */
    private function individualPlan(User $user): ?IndividualSubscription
    {
        return $user->hotel_id === null ? $user->individualSubscription : null;
    }

    /** The daily AI turn cap: an individual's own, else the platform one. */
    private function dailyTurnsFor(User $user): int
    {
        $individual = $this->individualPlan($user);

        return $individual !== null && $individual->daily_ai_turns !== null
            ? $individual->daily_ai_turns
            : self::perEmployeeDailyTurns();
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

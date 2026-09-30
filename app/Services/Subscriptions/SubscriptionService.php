<?php

namespace App\Services\Subscriptions;

use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Super Admin plan catalog and hotel assignments (SUB-01..05, AIL-01). */
final class SubscriptionService
{
    /** @param array{name: string, employee_limit: int, price_dzd: int, price_usd?: float, extra_points_price_dzd?: int, extra_points_price_usd?: float, extra_seat_price_dzd?: int, extra_seat_price_usd?: float, points_per_employee: int, bonus_points_per_employee: int, voice_points_per_10_minutes: int, ai_action_points: int, is_active: bool} $data */
    public function updatePlan(SubscriptionPlan $plan, array $data): SubscriptionPlan
    {
        return DB::transaction(function () use ($plan, $data): SubscriptionPlan {
            // An individual plan is one learner with an AI point allowance:
            // never seats, never a shared pool (client request 2026-09-27).
            if ($plan->isIndividual()) {
                $data['employee_limit'] = 1;
                $data['bonus_points_per_employee'] = 0;
            }

            // Hotels always need a plan to sign up on. Individual plans may
            // all be switched off, which hides the individual offer.
            if (! $plan->isIndividual() && ! $data['is_active'] && $plan->is_active && SubscriptionPlan::query()->active()->forHotels()->count() <= 1) {
                throw ValidationException::withMessages([
                    'is_active' => __('At least one plan must stay available for new hotel subscriptions.'),
                ]);
            }

            $before = $plan->only([
                'name', 'employee_limit', 'price_dzd', 'price_usd', 'extra_points_price_dzd', 'extra_points_price_usd',
                'extra_seat_price_dzd', 'extra_seat_price_usd', 'points_per_employee',
                'bonus_points_per_employee', 'voice_points_per_10_minutes', 'ai_action_points', 'is_active',
            ]);
            $plan->fill($data)->save();

            AuditLog::record($plan, 'subscription.plan_updated', [
                'before' => $before,
                'after' => $plan->only(array_keys($before)),
            ]);

            return $plan;
        });
    }

    public function assignPlan(Hotel $hotel, SubscriptionPlan $plan): Hotel
    {
        return DB::transaction(function () use ($hotel, $plan): Hotel {
            /** @var Hotel $locked */
            $locked = Hotel::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($hotel->id);
            $before = $locked->subscription_plan_id;

            if ($before === $plan->id) {
                return $locked;
            }

            $locked->subscription_plan_id = $plan->id;
            $locked->save();

            AuditLog::record($locked, 'hotel.subscription_plan_changed', [
                'from_plan_id' => $before,
                'to_plan_id' => $plan->id,
                'employee_limit' => $plan->employee_limit,
                'price_dzd' => $plan->price_dzd,
            ]);

            return $locked;
        });
    }
}

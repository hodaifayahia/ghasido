<?php

namespace App\Services\Subscriptions;

use App\Enums\Role;
use App\Models\AiUsage;
use App\Models\Hotel;
use App\Models\RoleplayAttempt;
use App\Models\User;
use App\Services\Ai\UsageMeter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

/** The signed-in employee's or hotel manager/admin's current-month AI balance (AIL-01, ROLE-02). */
final class AiPointsBalanceService
{
    public function __construct(
        private readonly UsageMeter $usage,
        private readonly HotelAiPointTopUpService $topUps,
    ) {}

    /**
     * @return array{role: 'employee'|'manager', total: int, used: int, remaining: int, percent: int}|null
     */
    public function forUser(User $user): ?array
    {
        if ($user->hasRole(Role::Employee->value)) {
            return $this->balance('employee', $user->ai_points_allocated, $this->usage->pointsUsedThisMonth($user));
        }

        if (! $user->hasAnyRole([Role::Admin->value, Role::Manager->value]) || $user->hotel_id === null) {
            return null;
        }

        $hotel = Hotel::query()
            ->withoutGlobalScopes()
            ->with('subscriptionPlan')
            ->find($user->hotel_id);

        if ($hotel === null) {
            return null;
        }

        $monthStart = Date::now()->startOfMonth();
        $total = $this->topUps->poolFor($hotel, $hotel->subscriptionPlan);
        $actionPoints = (int) AiUsage::query()
            ->where('hotel_id', $hotel->id)
            ->where('occurred_at', '>=', $monthStart)
            ->sum('points_charged');
        $voicePoints = (int) RoleplayAttempt::query()
            ->where('channel', RoleplayAttempt::CHANNEL_VOICE_CALL)
            ->where('is_preview', false)
            ->where('started_at', '>=', $monthStart)
            ->whereHas('user', fn (Builder $employees) => $employees
                ->where('hotel_id', $hotel->id)
                ->whereHas('roles', fn (Builder $roles) => $roles->where('name', Role::Employee->value)))
            ->sum('ai_points_charged');

        return $this->balance('manager', $total, $actionPoints + $voicePoints);
    }

    /**
     * @param  'employee'|'manager'  $role
     * @return array{role: 'employee'|'manager', total: int, used: int, remaining: int, percent: int}
     */
    private function balance(string $role, int $total, int $used): array
    {
        $remaining = max(0, $total - $used);

        return [
            'role' => $role,
            'total' => $total,
            'used' => $used,
            'remaining' => $remaining,
            'percent' => $total > 0 ? (int) floor($remaining / $total * 100) : 0,
        ];
    }
}

<?php

namespace App\Services\Subscriptions;

use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelAiPointTopUp;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/** Paid monthly additions to a hotel's employee AI point pool (AIL-01, ADM-03). */
final class HotelAiPointTopUpService
{
    public function __construct(private readonly HotelAiPointTopUpRequestService $requests) {}

    public function poolFor(Hotel $hotel, ?SubscriptionPlan $plan = null): int
    {
        $base = ($plan ?? $hotel->subscriptionPlan)?->pointsPool() ?? 0;

        return $base + $this->paidPointsForCurrentMonth($hotel);
    }

    public function paidPointsForCurrentMonth(Hotel $hotel): int
    {
        return (int) $hotel->aiPointTopUps()
            ->whereDate('month_start', Date::now()->startOfMonth()->toDateString())
            ->sum('points');
    }

    /**
     * @param  array{currency: 'DZD'|'USD', amount: int|float}  $payment  DZD for Algerian hotels, USD for international ones
     */
    public function recordPayment(
        Hotel $hotel,
        int $points,
        array $payment,
        ?int $paymentMethodId,
        ?string $paymentReference,
        User $actor,
    ): HotelAiPointTopUp {
        return DB::transaction(function () use ($hotel, $points, $payment, $paymentMethodId, $paymentReference, $actor): HotelAiPointTopUp {
            /** @var Hotel $lockedHotel */
            $lockedHotel = Hotel::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($hotel->id);
            $monthStart = Date::now()->startOfMonth();

            $topUp = HotelAiPointTopUp::query()->create([
                'hotel_id' => $lockedHotel->id,
                'month_start' => $monthStart->toDateString(),
                'points' => $points,
                'currency' => $payment['currency'],
                'amount_dzd' => $payment['currency'] === 'DZD' ? (int) $payment['amount'] : 0,
                'amount_usd' => $payment['currency'] === 'USD' ? (float) $payment['amount'] : null,
                'payment_method_id' => $paymentMethodId,
                'payment_reference' => $paymentReference,
                'received_by' => $actor->id,
                'received_at' => Date::now(),
            ]);

            AuditLog::record($topUp, 'hotel.ai_points_topped_up', [
                'hotel_id' => $lockedHotel->id,
                'month_start' => $monthStart->toDateString(),
                'points' => $points,
                'currency' => $payment['currency'],
                'amount' => $payment['amount'],
                'payment_method_id' => $paymentMethodId,
                'payment_reference' => $paymentReference,
                'received_by' => $actor->id,
            ]);

            $this->requests->fulfillPending($lockedHotel, $topUp->id);

            return $topUp;
        });
    }
}

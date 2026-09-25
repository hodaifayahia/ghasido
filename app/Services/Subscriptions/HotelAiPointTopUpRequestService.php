<?php

namespace App\Services\Subscriptions;

use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelAiPointTopUpRequest;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/** Hotel recharge requests shown to the platform Super Admin (AIL-01, ADM-03). */
final class HotelAiPointTopUpRequestService
{
    public function requestForHotel(Hotel $hotel, User $requester): HotelAiPointTopUpRequest
    {
        return DB::transaction(function () use ($hotel, $requester): HotelAiPointTopUpRequest {
            /** @var Hotel $lockedHotel */
            $lockedHotel = Hotel::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($hotel->id);
            $monthStart = Date::now()->startOfMonth();

            $existing = HotelAiPointTopUpRequest::query()
                ->where('hotel_id', $lockedHotel->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $topUpRequest = HotelAiPointTopUpRequest::query()->create([
                'hotel_id' => $lockedHotel->id,
                'requested_by' => $requester->id,
                'month_start' => $monthStart->toDateString(),
                'status' => 'pending',
            ]);

            AuditLog::record($topUpRequest, 'hotel.ai_points_top_up_requested', [
                'hotel_id' => $lockedHotel->id,
                'requested_by' => $requester->id,
                'month_start' => $monthStart->toDateString(),
            ]);

            return $topUpRequest;
        });
    }

    public function fulfillPending(Hotel $hotel, int $topUpId): void
    {
        HotelAiPointTopUpRequest::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'fulfilled',
                'top_up_id' => $topUpId,
                'fulfilled_at' => Date::now(),
                'updated_at' => Date::now(),
            ]);
    }
}

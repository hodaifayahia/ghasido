<?php

namespace App\Http\Controllers\Admin\Hotels;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Hotels\SyncSeatQuotasRequest;
use App\Models\Hotel;
use App\Services\Hotels\SeatQuotaService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Manage seats: the per department quotas of one hotel (SUB-01, SUB-03;
 * spec 0002, AC-6, AC-7).
 */
class HotelSeatQuotasController extends Controller
{
    public function update(SyncSeatQuotasRequest $request, Hotel $hotel, SeatQuotaService $seats): RedirectResponse
    {
        $changes = $seats->sync($hotel, $request->quotas());

        Inertia::flash('toast', [
            'type' => $changes === [] ? 'info' : 'success',
            'message' => $changes === []
                ? __('No seat quota changed for :hotel.', ['hotel' => $hotel->name])
                : __('Seat quotas for :hotel were saved.', ['hotel' => $hotel->name]),
        ]);

        return back();
    }
}

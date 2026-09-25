<?php

namespace App\Http\Controllers\Admin\Hotels;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Hotels\ArchiveHotelRequest;
use App\Models\Hotel;
use App\Services\Hotels\HotelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Pause, resume and archive: the access changes on a hotel (SUB-06, SUB-08;
 * spec 0002, AC-5, contracts and seats child).
 */
class HotelAccessController extends Controller
{
    public function pause(Hotel $hotel, HotelService $hotels): RedirectResponse
    {
        Gate::authorize('update', $hotel);

        $hotels->pause($hotel);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Access to :hotel is paused. The contract clock is frozen.', ['hotel' => $hotel->name]),
        ]);

        return back();
    }

    public function resume(Hotel $hotel, HotelService $hotels): RedirectResponse
    {
        Gate::authorize('update', $hotel);

        $hotels->resume($hotel);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Access to :hotel is resumed. The paused days were added back.', ['hotel' => $hotel->name]),
        ]);

        return back();
    }

    public function archive(ArchiveHotelRequest $request, Hotel $hotel, HotelService $hotels): RedirectResponse
    {
        $hotels->archive($hotel, $request->reason());

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => __(':hotel was archived. Nothing was deleted.', ['hotel' => $hotel->name]),
        ]);

        return back();
    }
}

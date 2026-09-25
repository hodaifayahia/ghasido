<?php

namespace App\Http\Controllers\Admin\Hotels;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Hotels\ExtendContractRequest;
use App\Models\Hotel;
use App\Services\Hotels\HotelService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Extend Contract: move the end date (SUB-04, SUB-06; spec 0002, contracts
 * and seats child, step 3).
 */
class HotelContractController extends Controller
{
    public function update(ExtendContractRequest $request, Hotel $hotel, HotelService $hotels): RedirectResponse
    {
        $hotels->extendContract($hotel, $request->endsOn());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('The contract for :hotel now ends on :date.', [
                'hotel' => $hotel->name,
                'date' => $request->endsOn()->format('d M Y'),
            ]),
        ]);

        return back();
    }
}

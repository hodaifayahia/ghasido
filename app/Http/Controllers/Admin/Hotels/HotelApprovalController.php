<?php

namespace App\Http\Controllers\Admin\Hotels;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Hotels\RejectHotelRequest;
use App\Models\Hotel;
use App\Models\User;
use App\Services\Hotels\HotelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Approve or reject a pending hotel (spec 0002, AC-13, AC-14).
 *
 * Both sit behind permission:hotels.approve, a capability the platform owner
 * alone holds: a hotel cannot approve itself.
 */
class HotelApprovalController extends Controller
{
    public function approve(Request $request, Hotel $hotel, HotelService $hotels): RedirectResponse
    {
        Gate::authorize('approve', $hotel);

        /** @var User $approver */
        $approver = $request->user();

        $hotels->approve($hotel, $approver);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':hotel is approved. Its contract runs from today.', ['hotel' => $hotel->name]),
        ]);

        return back();
    }

    public function reject(RejectHotelRequest $request, Hotel $hotel, HotelService $hotels): RedirectResponse
    {
        $hotels->reject($hotel, $request->reason(), $request->user('web'));

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => __(':hotel was rejected and archived.', ['hotel' => $hotel->name]),
        ]);

        return back();
    }
}

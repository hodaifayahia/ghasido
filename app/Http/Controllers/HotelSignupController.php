<?php

namespace App\Http\Controllers;

use App\Http\Requests\HotelSignupRequest;
use App\Services\Hotels\HotelService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public hotel onboarding.
 *
 * This is a hotel request, not public employee self-registration: the guest
 * submits the hotel's details plus the first manager account, the hotel lands
 * pending, and the manager stays inactive until a Super Admin approves it
 * (AUTH-02, AUTH-08, SUB-04; user request 2026-09-20).
 */
class HotelSignupController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/HotelSignup');
    }

    public function store(HotelSignupRequest $request, HotelService $hotels): RedirectResponse
    {
        $hotel = $hotels->requestAccess(
            $request->hotelData(),
            $request->managerData(),
        );

        return to_route('login')->with('status', __('Your hotel request for :hotel has been received. We will email you when a Super Admin activates your manager account.', [
            'hotel' => $hotel->name,
        ]));
    }
}

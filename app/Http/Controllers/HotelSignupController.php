<?php

namespace App\Http\Controllers;

use App\Http\Requests\HotelSignupRequest;
use App\Models\User;
use App\Services\Hotels\HotelService;
use App\Services\Meaning\HelperLanguages;
use App\Services\Meaning\RequestedHelperLanguages;
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
        return Inertia::render('auth/HotelSignup', [
            'helperLanguages' => app(HelperLanguages::class)->options(),
        ]);
    }

    public function store(HotelSignupRequest $request, HotelService $hotels, RequestedHelperLanguages $helperLanguages): RedirectResponse
    {
        $hotel = $hotels->requestAccess(
            $request->hotelData(),
            $request->managerData(),
        );

        // The helper languages the hotel asked for (client request 2026-10-01).
        $manager = User::query()->where('hotel_id', $hotel->id)->orderBy('id')->first();

        if ($manager !== null) {
            $helperLanguages->apply($manager, $request->requestedHelperLanguages());
        }

        return to_route('login')->with('status', __('Your hotel request for :hotel has been received. We will email you when a Super Admin activates your manager account.', [
            'hotel' => $hotel->name,
        ]));
    }
}

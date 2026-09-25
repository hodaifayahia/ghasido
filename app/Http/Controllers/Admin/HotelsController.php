<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Hotels\StoreHotelRequest;
use App\Http\Requests\Admin\Hotels\UpdateHotelRequest;
use App\Models\Hotel;
use App\Models\User;
use App\Services\Hotels\HotelDetails;
use App\Services\Hotels\HotelDirectory;
use App\Services\Hotels\HotelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Super Admin hotels screen (ORG-01, SUB-01, SUB-03, SUB-04, SUB-06,
 * SUB-07, SUB-08, REP-01, ADM-02; spec 0002).
 *
 * Read → authorize → delegate. No query is written here (AC-20): the
 * directory service reads through model methods and resources, the form
 * requests validate, and HotelService performs every change with its audit
 * row.
 */
class HotelsController extends Controller
{
    public function index(Request $request, HotelDirectory $directory): Response
    {
        // The route already carries permission:hotels.view. The policy check
        // is the authorization proper, so it holds even if the route's
        // middleware is ever changed (spec 0002, Security model).
        Gate::authorize('viewAny', Hotel::class);

        return Inertia::render('admin/Hotels', $directory->build($request));
    }

    public function show(Request $request, Hotel $hotel, HotelDetails $details): Response
    {
        Gate::authorize('view', $hotel);

        /** @var User $viewer */
        $viewer = $request->user();

        return Inertia::render('admin/HotelDetails', $details->build($request, $hotel, $viewer));
    }

    public function store(StoreHotelRequest $request, HotelService $hotels): RedirectResponse
    {
        $hotel = $hotels->create($request->hotelData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':hotel was created and is waiting for approval.', ['hotel' => $hotel->name]),
        ]);

        return to_route('hotels', ['hotel' => $hotel->id]);
    }

    public function update(UpdateHotelRequest $request, Hotel $hotel, HotelService $hotels): RedirectResponse
    {
        $hotels->update($hotel, $request->hotelData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':hotel was updated.', ['hotel' => $hotel->name]),
        ]);

        return back();
    }
}

<?php

namespace App\Http\Controllers\Admin\Hotels;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Hotels\AddHotelDepartmentRequest;
use App\Models\Department;
use App\Models\Hotel;
use App\Services\Hotels\HotelDepartmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * The hotel row action "Departments": add a department to a hotel, or take
 * one off it (SUB-01, ORG-02, ORG-03).
 */
class HotelDepartmentsController extends Controller
{
    public function store(AddHotelDepartmentRequest $request, Hotel $hotel, HotelDepartmentService $service): RedirectResponse
    {
        $department = $service->add($hotel, $request->department(), $request->newName(), $request->seats());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':department was added to :hotel.', ['department' => $department->name, 'hotel' => $hotel->name]),
        ]);

        return back();
    }

    public function destroy(Hotel $hotel, Department $department, HotelDepartmentService $service): RedirectResponse
    {
        Gate::authorize('manageSeats', $hotel);

        $service->remove($hotel, $department);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':department was removed from :hotel.', ['department' => $department->name, 'hotel' => $hotel->name]),
        ]);

        return back();
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\User;
use App\Services\Subscriptions\EmployeeAiPointsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Manager controls only their own hotel's employee AI point pool (ROLE-02, AIL-01). */
final class EmployeeAiPointsController extends Controller
{
    public function index(Request $request, EmployeeAiPointsService $points): Response
    {
        Gate::authorize(Permission::AiPointsManage->value);

        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->hasRole(Role::Manager->value) && $actor->hotel_id !== null, 403);

        $hotel = Hotel::query()->withoutGlobalScopes()->findOrFail($actor->hotel_id);

        return Inertia::render('manager/AiPoints', $points->forHotel($hotel));
    }

    public function update(Request $request, User $employee, EmployeeAiPointsService $points): RedirectResponse
    {
        Gate::authorize(Permission::AiPointsManage->value);

        /** @var User $actor */
        $actor = $request->user();
        abort_unless(
            $actor->hasRole(Role::Manager->value)
                && $actor->hotel_id !== null
                && $employee->hotel_id === $actor->hotel_id
                && $employee->hasRole(Role::Employee->value),
            403,
        );

        $data = $request->validate([
            'ai_points_allocated' => ['required', 'integer', 'min:0', 'max:100000000'],
        ]);

        $hotel = Hotel::query()->withoutGlobalScopes()->findOrFail($actor->hotel_id);
        $points->updateAllocation($hotel, $employee, (int) $data['ai_points_allocated'], $actor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('AI points for :employee were updated.', ['employee' => $employee->name]),
        ]);

        return back();
    }
}

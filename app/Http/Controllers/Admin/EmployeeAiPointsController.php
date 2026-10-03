<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\User;
use App\Services\Subscriptions\EmployeeAiPointsService;
use App\Services\Subscriptions\HotelAiPointTopUpRequestService;
use App\Services\Subscriptions\HotelAiPointTopUpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Hotel managers/admins control only their own employee AI point pool (ROLE-02, AIL-01). */
final class EmployeeAiPointsController extends Controller
{
    public function index(Request $request, EmployeeAiPointsService $points): Response
    {
        Gate::authorize(Permission::AiPointsManage->value);

        /** @var User $actor */
        $actor = $request->user();

        // The Super Admin shares any hotel's points too, picking the hotel
        // (client request 2026-10-02: "a section like the manager's").
        if ($actor->hasRole(Role::SuperAdmin->value)) {
            $hotels = Hotel::query()->withoutGlobalScopes()->notArchived()->whereNotNull('subscription_plan_id')->orderBy('name')->get(['id', 'name']);
            abort_if($hotels->isEmpty(), 404, __('No hotel has a subscription plan yet.'));

            $hotel = Hotel::query()->withoutGlobalScopes()->find((int) $request->query('hotel', 0))
                ?? Hotel::query()->withoutGlobalScopes()->findOrFail($hotels->firstOrFail()->id);

            return Inertia::render('manager/AiPoints', [
                ...$points->forHotel($hotel),
                'hotelPicker' => [
                    'current' => $hotel->id,
                    'options' => $hotels->map(fn (Hotel $option): array => ['value' => $option->id, 'label' => $option->name])->values()->all(),
                ],
            ]);
        }

        abort_unless(
            $actor->hasAnyRole([Role::Admin->value, Role::Manager->value])
                && $actor->hotel_id !== null,
            403,
        );

        $hotel = Hotel::query()->withoutGlobalScopes()->findOrFail($actor->hotel_id);

        return Inertia::render('manager/AiPoints', $points->forHotel($hotel));
    }

    public function update(Request $request, User $employee, EmployeeAiPointsService $points): RedirectResponse
    {
        Gate::authorize(Permission::AiPointsManage->value);

        /** @var User $actor */
        $actor = $request->user();
        $superAdmin = $actor->hasRole(Role::SuperAdmin->value);
        abort_unless(
            $employee->hasRole(Role::Employee->value)
                && $employee->hotel_id !== null
                && ($superAdmin || (
                    $actor->hasAnyRole([Role::Admin->value, Role::Manager->value])
                    && $actor->hotel_id !== null
                    && $employee->hotel_id === $actor->hotel_id
                )),
            403,
        );

        $data = $request->validate([
            'ai_points_allocated' => ['required', 'integer', 'min:0', 'max:100000000'],
        ]);

        $hotel = Hotel::query()->withoutGlobalScopes()->findOrFail($employee->hotel_id);
        $points->updateAllocation($hotel, $employee, (int) $data['ai_points_allocated'], $actor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('AI points for :employee were updated.', ['employee' => $employee->name]),
        ]);

        return back();
    }

    /**
     * Free extra points for a hotel, outside its plan, by the Super Admin
     * only (client request 2026-10-03).
     */
    public function bonus(Request $request, HotelAiPointTopUpService $topUps): RedirectResponse
    {
        Gate::authorize(Permission::AiPointsManage->value);

        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->hasRole(Role::SuperAdmin->value), 403);

        $data = $request->validate([
            'hotel_id' => ['required', 'integer'],
            'points' => ['required', 'integer', 'min:1', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:120'],
        ]);

        $hotel = Hotel::query()->withoutGlobalScopes()->notArchived()->findOrFail((int) $data['hotel_id']);
        $topUps->grantBonus($hotel, (int) $data['points'], isset($data['note']) ? (string) $data['note'] : null, $actor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':points extra points were added to :hotel for this month.', [
                'points' => number_format((int) $data['points']),
                'hotel' => $hotel->name,
            ]),
        ]);

        return back();
    }

    /** Ask the platform Super Admin to replenish an exhausted hotel pool (AIL-01, ROLE-02). */
    public function requestTopUp(
        Request $request,
        EmployeeAiPointsService $points,
        HotelAiPointTopUpRequestService $requests,
    ): RedirectResponse {
        Gate::authorize(Permission::AiPointsManage->value);

        /** @var User $actor */
        $actor = $request->user();
        abort_unless(
            $actor->hasAnyRole([Role::Admin->value, Role::Manager->value])
                && $actor->hotel_id !== null,
            403,
        );

        $hotel = Hotel::query()->withoutGlobalScopes()->notArchived()->findOrFail($actor->hotel_id);
        abort_if($points->forHotel($hotel)['summary']['remaining'] > 0, 409, __('Your hotel AI point pool is not empty yet.'));
        $requests->requestForHotel($hotel, $actor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('A point recharge request was sent to the platform admin.'),
        ]);

        return back();
    }
}

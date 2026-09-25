<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\HotelAiPointTopUpRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Read a hotel recharge request from the platform notification center (ROLE-03). */
final class HotelAiPointTopUpRequestController extends Controller
{
    public function read(Request $request, HotelAiPointTopUpRequest $topUpRequest): RedirectResponse
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor->hasRole(Role::SuperAdmin->value), 403);

        $topUpRequest->markRead();

        return back();
    }
}

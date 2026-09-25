<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Hotel;
use App\Models\User;
use App\Services\Dashboard\DashboardBriefing;
use App\Services\Dashboard\DashboardStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform and hotel management dashboards (ADM-01, REP-01; spec 0003 Part D).
 *
 * Read → authorize → delegate. Every figure comes from DashboardStats, which
 * reads real rows; the prop shapes are the contract in
 * resources/js/types/dashboard.ts.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStats $stats, DashboardBriefing $briefing): Response|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        // An employee's home is the learner area (spec 0003 Part E): Fortify
        // lands every login here, and the learner shell takes it from there.
        if ($user?->hasRole(Role::Employee->value)) {
            return to_route('learn.home');
        }

        if ($user?->hasAnyRole([Role::Admin->value, Role::Manager->value])) {
            // Hotel admins and managers receive the same operational dashboard
            // surface, but every figure is built from their own hotel only
            // (ROLE-02, REP-01).
            // An account without a tenant is deliberately not given
            // the unscoped dashboard.
            $hotel = $user->hotel_id === null
                ? null
                : Hotel::withoutGlobalScopes()->find($user->hotel_id);

            if ($hotel !== null) {
                $built = $stats->build($hotel);

                return Inertia::render('Dashboard', [
                    ...$built,
                    // AI briefing on this hotel's aggregate figures only
                    // (spec 0005 §4.1; ROLE-02).
                    'briefing' => $briefing->present($hotel, $built),
                ]);
            }

            return Inertia::render('Placeholder', [
                'title' => __('Your hotel account is not assigned to a hotel'),
                'body' => __('Ask a platform administrator to assign your account to a hotel before opening the dashboard.'),
            ]);
        }

        if (! $user?->hasRole(Role::SuperAdmin->value)) {
            return Inertia::render('Placeholder', [
                'title' => __('Your area is being prepared'),
                'body' => __('Your training space is not ready yet. Your hotel administrator will let you know when it opens.'),
            ]);
        }

        $built = $stats->build();

        return Inertia::render('Dashboard', [
            ...$built,
            'briefing' => $briefing->present(null, $built),
        ]);
    }
}

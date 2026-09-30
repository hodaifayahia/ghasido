<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Platform\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Learning (client request 2026-09-30; Super Admin only): the
 * Pre-test score that suggests moving up a level. Stored, not coded, so the
 * Super Admin changes it without a developer (ADM-02). Guarded by the
 * `manage-learning-settings` gate on the route and again here (ROLE-01).
 */
final class LearningSettingsController extends Controller
{
    public function edit(PlatformSettings $settings): Response
    {
        Gate::authorize('manage-learning-settings');

        return Inertia::render('settings/Learning', [
            'levelUpFrom' => $settings->levelUpFrom(),
        ]);
    }

    public function update(Request $request, PlatformSettings $settings): RedirectResponse
    {
        Gate::authorize('manage-learning-settings');

        $data = $request->validate([
            'level_up_from' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var User $user */
        $user = $request->user('web');
        $settings->setLevelUpFrom((int) $data['level_up_from'], $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Learning settings saved.')]);

        return back();
    }
}

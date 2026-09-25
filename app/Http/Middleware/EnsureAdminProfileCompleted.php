<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every admin account completes its contact profile before using the app
 * (owner request 2026-09-25): first and last name, email, phone and
 * address. The platform needs a way to reach them, for example when the AI
 * credit runs low (spec 0007, D12).
 *
 * Page visits are sent to Settings → Profile until the profile is done.
 * The settings pages themselves (profile, password, two-factor), signing
 * out, the owner console and JSON calls stay open, and form posts pass:
 * the pages behind them cannot be reached anyway.
 */
class EnsureAdminProfileCompleted
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if (! $user instanceof User
            || ! $request->isMethod('GET')
            || $this->alwaysOpen($request)
            || ! $user->adminProfileIncomplete()) {
            return $next($request);
        }

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => __('Please complete your profile first: first and last name, email, phone and address.'),
        ]);

        return redirect()->route('profile.edit');
    }

    private function alwaysOpen(Request $request): bool
    {
        return $request->is('settings', 'settings/*', 'owner', 'owner/*', 'logout', 'user/*', 'two-factor*', 'up')
            || ($request->wantsJson() && ! $request->header('X-Inertia'));
    }
}

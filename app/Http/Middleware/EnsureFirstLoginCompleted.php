<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The first-login gate (AUTH-04, AUTH-05, PRIV-01; spec 0003 Part E).
 *
 * An employee who has not been through the first-login screen (email,
 * reminder consent, research notice) is sent there from every learner route
 * until `first_login_completed_at` is set. The first-login routes themselves
 * sit outside this middleware, and are skipped here as well in case a group
 * ever wraps them.
 */
class EnsureFirstLoginCompleted
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->hasRole(Role::Employee->value)) {
            return $next($request);
        }

        // A level is part of the setup too (client decision 2026-09-30), so
        // an employee who finished first login before levels existed chooses
        // one on the same screen.
        if (($user->hasCompletedFirstLogin() && $user->english_level !== null) || $request->routeIs('learn.first-login.*')) {
            return $next($request);
        }

        return redirect()->route('learn.first-login.edit');
    }
}

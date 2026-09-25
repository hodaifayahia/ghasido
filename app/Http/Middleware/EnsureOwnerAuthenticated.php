<?php

namespace App\Http\Middleware;

use App\Models\Owner;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The owner console's boundary (spec 0007, D1): only a session on the
 * `owner` guard passes. A signed-in app user, the Super Admin included,
 * is on the `web` guard and is sent to the owner login like a guest.
 */
class EnsureOwnerAuthenticated
{
    /** Session key of the owner page to return to after signing in. */
    public const string INTENDED = 'owner.url.intended';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $owner = Auth::guard('owner')->user();

        if (! $owner instanceof Owner) {
            if ($request->expectsJson()) {
                abort(401);
            }

            // Its own "go back here after sign-in" key: Laravel's shared
            // `url.intended` belongs to the app login, and mixing the two
            // sent an owner to /dashboard (then the app login) after
            // signing in, and an app user to the owner login.
            if ($request->isMethod('GET')) {
                $request->session()->put(self::INTENDED, $request->fullUrl());
            }

            return redirect()->route('owner.login');
        }

        // Only the owner layout reads this, so it is shared here rather than
        // on every app response.
        Inertia::share('owner', ['name' => $owner->name, 'email' => $owner->email]);

        return $next($request);
    }
}

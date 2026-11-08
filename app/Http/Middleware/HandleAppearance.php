<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Guesvia has a light-only design system (AGENTS.md §3), so the app
        // must not follow the OS dark preference. Default to light; the
        // appearance toggle still honours an explicit dark choice on the
        // starter's auth/settings screens.
        // The cookie is not encrypted and reaches an inline script: only
        // the three known values pass (security audit 2026-11-08).
        $appearance = $request->cookie('appearance');

        View::share('appearance', in_array($appearance, ['light', 'dark', 'system'], true) ? $appearance : 'light');

        return $next($request);
    }
}

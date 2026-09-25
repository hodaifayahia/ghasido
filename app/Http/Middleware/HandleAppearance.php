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
        View::share('appearance', $request->cookie('appearance') ?? 'light');

        return $next($request);
    }
}

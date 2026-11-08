<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logging out also wipes the encrypted page history, so pressing Back on a
 * shared hotel device cannot show the previous user's pages from the
 * browser's history state (security audit 2026-11-08; PRIV-04).
 */
class LogoutResponse implements LogoutResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        Inertia::clearHistory();

        return redirect(Fortify::redirects('logout', '/'));
    }
}

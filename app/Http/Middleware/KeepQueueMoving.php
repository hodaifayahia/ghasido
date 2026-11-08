<?php

namespace App\Http\Middleware;

use App\Support\QueueFallback;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On a signed-in request, start the queue fallback when no worker runs
 * (App\Support\QueueFallback; client report 2026-11-08). The check is one
 * cache read, plus one indexed query only while no worker is alive.
 */
class KeepQueueMoving
{
    public function __construct(private readonly QueueFallback $fallback) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user('web') !== null) {
            $this->fallback->kick();
        }

        return $response;
    }
}

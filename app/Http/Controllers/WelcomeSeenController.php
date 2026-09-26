<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Records that the user has seen the one-time welcome animation, so it
 * never plays again on any device (client request 2026-09-26).
 */
final class WelcomeSeenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('web');

        if ($user->welcomed_at === null) {
            $user->forceFill(['welcomed_at' => now()])->saveQuietly();
        }

        return response()->json(['welcomed' => true]);
    }
}

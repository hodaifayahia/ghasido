<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\OwnerLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sign in and out of the owner console (spec 0007, D1). Its own guard and
 * its own form, next to Fortify's app login: owners are not users.
 */
class OwnerSessionController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (Auth::guard('owner')->check()) {
            return to_route('owner.dashboard');
        }

        return Inertia::render('owner/Login');
    }

    public function store(OwnerLoginRequest $request): RedirectResponse
    {
        $owner = $request->authenticate();

        $request->session()->regenerate();
        $owner->forceFill(['last_login_at' => Date::now()])->save();

        return redirect()->intended(route('owner.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('owner')->logout();

        // Only the owner's keys are dropped: an app session in the same
        // browser (the web guard) stays signed in.
        $request->session()->regenerateToken();

        return to_route('owner.login');
    }
}

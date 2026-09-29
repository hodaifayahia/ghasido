<?php

namespace App\Http\Middleware;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a signed in session from outliving its hotel's access (AUTH-08,
 * SUB-05, SUB-08; spec 0003 Part E).
 *
 * Fortify::authenticateUsing() refuses a NEW login for a deactivated account
 * or a hotel that no longer allows access. This middleware covers the
 * session that already existed when the contract ended or the account was
 * switched off: the user is logged out and sent to the login page with the
 * same message they would have seen there. Nothing is deleted (DATA-10).
 *
 * Users with no hotel (the Super Admin) pass through untouched.
 */
class EnsureHotelAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $message = self::blockedMessageFor($user);

        if ($message === null) {
            return $next($request);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withErrors([Fortify::username() => $message]);
    }

    /**
     * Why this user may not be signed in right now, or null when they may.
     *
     * The one place the rule is written: the login callback and this
     * middleware both read it, so a learner is refused for the same reason
     * whether they are signing in or already in (spec 0002, AC-15).
     */
    public static function blockedMessageFor(User $user): ?string
    {
        $hotel = $user->hotel;

        if (
            $user->status === AccountStatus::Inactive
            && $hotel !== null
            && ! $hotel->allowsAccess()
            && $user->hasRole(Role::Manager->value)
        ) {
            return $hotel->access_state->blockedMessage();
        }

        if ($user->status === AccountStatus::Inactive) {
            return __('This account has been deactivated. Please contact your administrator.');
        }

        // An individual subscriber's own access window stands in for the
        // hotel's contract (SUB-05); their data stays either way (DATA-10).
        $individual = $hotel === null ? $user->individualSubscription : null;

        if ($individual !== null && $individual->isOutsideWindow()) {
            return $individual->windowState() === 'upcoming'
                ? __('Your subscription starts on :date.', ['date' => $individual->starts_on?->toFormattedDateString()])
                : __('Your subscription ended on :date. Contact GHASIDO support to renew it.', ['date' => $individual->ends_on?->toFormattedDateString()]);
        }

        if ($hotel === null || $hotel->allowsAccess()) {
            return null;
        }

        return $hotel->access_state->blockedMessage();
    }
}

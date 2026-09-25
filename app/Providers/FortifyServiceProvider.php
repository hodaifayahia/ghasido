<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Middleware\EnsureHotelAccess;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     *
     * There is no createUsersUsing(): accounts are only ever created by the
     * Super Admin or a hotel manager (AUTH-02, AUTH-03).
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }

    /**
     * Username-or-email login (AUTH-01, AUTH-08, SUB-05; spec 0003 B.1).
     *
     * The posted field keeps its `email` name so the login form, the rate
     * limiter and the existing tests stay as they are; what it holds may be a
     * username or an email address. This callback REPLACES Fortify's own
     * credential check, so it verifies the password itself.
     *
     * A wrong identifier or password returns null and Fortify raises the
     * usual "these credentials do not match" error. A correct password on an
     * account that may not sign in (deactivated, or a hotel that is pending,
     * paused, archived or past its contract) raises the hotel's own message
     * on the same field, so the learner is told why rather than left to
     * retype a password that was right (spec 0002, AC-15).
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $identifier = Str::lower(trim((string) $request->input(Fortify::username())));
            $password = (string) $request->input('password');

            if ($identifier === '' || $password === '') {
                return null;
            }

            $user = User::query()
                ->where(function ($query) use ($identifier): void {
                    $query
                        ->where('username', $identifier)
                        ->orWhereRaw('LOWER(email) = ?', [$identifier]);
                })
                ->first();

            if ($user === null || ! Hash::check($password, $user->password)) {
                return null;
            }

            $blocked = EnsureHotelAccess::blockedMessageFor($user);

            if ($blocked !== null) {
                throw ValidationException::withMessages([
                    Fortify::username() => $blocked,
                ]);
            }

            return $user;
        });

        // `last_login_at` feeds the Needs Attention lists and the inactivity
        // reminders (DATA-06, REM-03). Written on the Login event rather than
        // in the callback above, so a two-factor challenge that is never
        // completed does not count as a login.
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}

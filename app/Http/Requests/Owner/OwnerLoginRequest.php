<?php

namespace App\Http\Requests\Owner;

use App\Models\Owner;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The owner console login (spec 0007, D1; SEC-02): five tries a minute per
 * e-mail and IP, like the app's own login limiter.
 */
class OwnerLoginRequest extends FormRequest
{
    public const int MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): Owner
    {
        $key = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            event(new Lockout($this));

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($key),
                    'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60),
                ]),
            ]);
        }

        $guard = Auth::guard('owner');

        if (! $guard->attempt(['email' => Str::lower($this->string('email')->trim()->value()), 'password' => $this->string('password')->value()])) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($key);

        $owner = $guard->user();

        if (! $owner instanceof Owner) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        return $owner;
    }

    private function throttleKey(): string
    {
        return 'owner-login|'.Str::transliterate(Str::lower($this->string('email')->value())).'|'.$this->ip();
    }
}

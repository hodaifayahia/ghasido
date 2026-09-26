<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the chosen interface language (I18N-02): the signed-in user's
 * saved choice first, so it follows them to every device, then the
 * `locale` cookie for guests, then English.
 */
class SetLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');
        $candidates = [
            $user instanceof User ? $user->locale : null,
            $request->cookie(Locales::COOKIE),
        ];

        $locale = collect($candidates)->first(fn (mixed $value): bool => Locales::isSupported($value))
            ?? Locales::DEFAULT;

        App::setLocale((string) $locale);
        View::share('locale', App::getLocale());
        View::share('direction', Locales::direction(App::getLocale()));

        return $next($request);
    }
}

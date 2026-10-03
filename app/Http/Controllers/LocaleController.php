<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\I18n\InterfaceLanguages;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Switch the interface language between English and Arabic (I18N-02). Open
 * to guests too, so the landing page can switch; a signed-in user's choice
 * is also saved on their account.
 */
final class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in(InterfaceLanguages::codes())],
        ]);

        $user = $request->user('web');

        if ($user instanceof User) {
            $user->forceFill(['locale' => $data['locale']])->save();
        }

        return back()->withCookie(cookie()->forever(Locales::COOKIE, $data['locale']));
    }

    /**
     * An added language's strings for the browser (client request
     * 2026-10-03); English and Arabic ship in the bundle instead.
     */
    public function messages(string $code): JsonResponse
    {
        abort_unless(Locales::isSupported($code), 404);

        return response()
            ->json((object) InterfaceLanguages::messages($code))
            ->header('Cache-Control', 'no-cache');
    }
}

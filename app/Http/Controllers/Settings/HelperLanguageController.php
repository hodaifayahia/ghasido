<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Meaning\HelperLanguages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Helper language (client request 2026-10-01): every signed-in
 * user (employee, individual, manager, admin, Super Admin) picks the
 * language Show Meaning explains English in, from the active languages
 * with their flags, and can change it at any time. Saved on the account,
 * so every device shows meanings in the same language.
 */
class HelperLanguageController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/HelperLanguage');
    }

    public function update(Request $request, HelperLanguages $languages): RedirectResponse
    {
        $data = $request->validate([
            'language' => ['required', 'string', Rule::in($languages->activeCodes())],
        ]);

        /** @var User $user */
        $user = $request->user('web');
        $from = $user->meaning_locale;
        $to = (string) $data['language'];

        $user->forceFill(['meaning_locale' => $to])->save();

        if ($from !== $to) {
            AuditLog::record($user, 'user.helper_language.changed', ['from' => $from, 'to' => $to]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Meanings are now shown in :language.', ['language' => $languages->name($to)])]);

        return back();
    }
}

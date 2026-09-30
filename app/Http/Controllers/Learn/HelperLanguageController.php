<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Meaning\HelperLanguages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * The learner's helper language for Show Meaning (client request
 * 2026-09-30): any active language the Super Admin offers. Saved on the
 * account, so every device shows meanings in the same language.
 */
class HelperLanguageController extends Controller
{
    public function update(Request $request, HelperLanguages $languages): RedirectResponse
    {
        $data = $request->validate([
            'language' => ['required', 'string', Rule::in($languages->activeCodes())],
        ]);

        /** @var User $user */
        $user = $request->user('web');
        $from = $user->meaning_locale;

        $user->forceFill(['meaning_locale' => (string) $data['language']])->save();

        AuditLog::record($user, 'learner.helper_language.changed', ['from' => $from, 'to' => $data['language']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Meanings are now shown in :language.', ['language' => $languages->name((string) $data['language'])])]);

        return back();
    }
}

<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Meaning\HelperLanguages;
use App\Services\Platform\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Learning (client request 2026-09-30; Super Admin only): the
 * Pre-test score that suggests moving up a level. Stored, not coded, so the
 * Super Admin changes it without a developer (ADM-02). Guarded by the
 * `manage-learning-settings` gate on the route and again here (ROLE-01).
 */
final class LearningSettingsController extends Controller
{
    public function edit(PlatformSettings $settings, HelperLanguages $languages): Response
    {
        Gate::authorize('manage-learning-settings');

        return Inertia::render('settings/Learning', [
            'levelUpFrom' => $settings->levelUpFrom(),
            'languages' => $languages->all(),
            'defaultLanguage' => $languages->defaultCode(),
        ]);
    }

    /**
     * The helper languages (client request 2026-09-30): add one, switch one
     * on or off, choose the default. A language is never removed, only
     * switched off, so its translations are kept (DATA-10).
     */
    public function languages(Request $request, HelperLanguages $languages): RedirectResponse
    {
        Gate::authorize('manage-learning-settings');

        $data = $request->validate([
            'languages' => ['required', 'array', 'min:1', 'max:30'],
            'languages.*.code' => ['required', 'string', 'regex:/^[a-z]{2,3}(-[a-z]{2,4})?$/', 'distinct'],
            'languages.*.name' => ['required', 'string', 'max:40'],
            'languages.*.native' => ['required', 'string', 'max:40'],
            'languages.*.dir' => ['required', Rule::in(['ltr', 'rtl'])],
            'languages.*.active' => ['required', 'boolean'],
            'default' => ['required', 'string'],
        ], [
            'languages.*.code.regex' => __('Use a short language code, like fr, ms, id or tr.'),
        ]);

        /** @var list<array{code: string, name: string, native: string, dir: string, active: bool}> $items */
        $items = array_map(fn (array $item): array => [
            'code' => strtolower(trim((string) $item['code'])),
            'name' => trim((string) $item['name']),
            'native' => trim((string) $item['native']),
            'dir' => $item['dir'] === 'rtl' ? 'rtl' : 'ltr',
            'active' => (bool) $item['active'],
        ], array_values($data['languages']));

        $codes = array_column($items, 'code');
        $active = array_column(array_filter($items, fn (array $item): bool => $item['active']), 'code');

        $missing = array_diff(array_column($languages->all(), 'code'), $codes);

        if ($missing !== []) {
            throw ValidationException::withMessages(['languages' => __('A language cannot be removed; switch it off instead.')]);
        }

        if ($active === []) {
            throw ValidationException::withMessages(['languages' => __('Keep at least one helper language on.')]);
        }

        if (! in_array($data['default'], $active, true)) {
            throw ValidationException::withMessages(['default' => __('The default must be a language that is on.')]);
        }

        /** @var User $user */
        $user = $request->user('web');
        $languages->save($items, (string) $data['default'], $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Helper languages saved.')]);

        return back();
    }

    public function update(Request $request, PlatformSettings $settings): RedirectResponse
    {
        Gate::authorize('manage-learning-settings');

        $data = $request->validate([
            'level_up_from' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var User $user */
        $user = $request->user('web');
        $settings->setLevelUpFrom((int) $data['level_up_from'], $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Learning settings saved.')]);

        return back();
    }
}

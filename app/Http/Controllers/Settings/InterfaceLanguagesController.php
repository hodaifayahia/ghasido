<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\InterfaceLanguage;
use App\Models\User;
use App\Services\I18n\InterfaceLanguageGenerator;
use App\Services\I18n\InterfaceLanguages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Settings → Interface languages (client request 2026-10-03): add any
 * language to the top language menu, have AI translate the whole interface
 * or write it by hand, correct any line, and switch it on when ready.
 * Super Admin only; the gate sits on the route and again here.
 */
final class InterfaceLanguagesController extends Controller
{
    private const int PER_PAGE = 25;

    public function index(Request $request): Response
    {
        Gate::authorize('manage-ai-models');

        $source = InterfaceLanguages::sourceStrings();
        $total = count($source);
        $languages = InterfaceLanguage::query()->orderBy('name')->get();
        $selected = $languages->firstWhere('code', (string) $request->query('language', ''));
        $editor = null;

        if ($selected instanceof InterfaceLanguage) {
            $search = trim((string) $request->query('search', ''));
            $filter = (string) $request->query('show', 'all');
            $messages = $selected->translations();

            $rows = collect($source)
                ->map(fn (string $english, string $key): array => [
                    'key' => $key,
                    'english' => $english,
                    'value' => $messages[$key] ?? '',
                ])
                ->filter(fn (array $row): bool => ($filter !== 'missing' || $row['value'] === '')
                    && ($search === '' || Str::contains($row['english'], $search, true) || Str::contains($row['value'], $search, true)))
                ->values();

            $lastPage = max(1, (int) ceil($rows->count() / self::PER_PAGE));
            $page = min(max(1, $request->integer('page', 1)), $lastPage);

            $editor = [
                'search' => $search,
                'show' => $filter === 'missing' ? 'missing' : 'all',
                'rows' => $rows->forPage($page, self::PER_PAGE)->values()->all(),
                'page' => $page,
                'lastPage' => $lastPage,
                'matching' => $rows->count(),
            ];
        }

        return Inertia::render('settings/InterfaceLanguages', [
            'languages' => $languages->map(fn (InterfaceLanguage $language): array => [
                'id' => $language->id,
                'code' => $language->code,
                'name' => $language->name,
                'native' => $language->native_name,
                'dir' => $language->direction,
                'enabled' => $language->enabled,
                'status' => $language->status,
                'failedReason' => $language->failed_reason,
                'translated' => count(array_intersect_key($language->translations(), $source)),
            ])->values()->all(),
            'total' => $total,
            'selected' => $selected?->code,
            'editor' => $editor,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-ai-models');

        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})?$/', Rule::notIn(['en', 'ar']), Rule::unique('interface_languages', 'code')],
            'name' => ['required', 'string', 'max:60'],
            'native_name' => ['required', 'string', 'max:60'],
            'direction' => ['required', Rule::in(['ltr', 'rtl'])],
        ], [
            'code.not_in' => __('English and Arabic are already built in.'),
        ]);

        /** @var User $actor */
        $actor = $request->user('web');

        $language = InterfaceLanguage::query()->create([
            ...$data,
            'enabled' => false,
            'messages' => [],
            'status' => 'draft',
            'created_by' => $actor->id,
        ]);

        AuditLog::record($language, 'interface_language.created');
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':language was added. Translate it with AI or by hand, then switch it on.', ['language' => $language->name])]);

        return to_route('interface-languages.index', ['language' => $language->code]);
    }

    public function update(Request $request, InterfaceLanguage $language): RedirectResponse
    {
        Gate::authorize('manage-ai-models');

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:60'],
            'native_name' => ['sometimes', 'required', 'string', 'max:60'],
            'direction' => ['sometimes', 'required', Rule::in(['ltr', 'rtl'])],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $language->update($data);
        AuditLog::record($language, 'interface_language.updated');

        if (array_key_exists('enabled', $data)) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $language->enabled
                ? __(':language is now in the language menu.', ['language' => $language->name])
                : __(':language was removed from the language menu.', ['language' => $language->name])]);
        }

        return back();
    }

    public function destroy(InterfaceLanguage $language): RedirectResponse
    {
        Gate::authorize('manage-ai-models');

        // Anyone using it goes back to English (the middleware ignores an
        // unknown language).
        $language->delete();
        AuditLog::record($language, 'interface_language.deleted');
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':language was deleted.', ['language' => $language->name])]);

        return to_route('interface-languages.index');
    }

    /** One round of AI translation; the page calls it until none are left. */
    public function generate(Request $request, InterfaceLanguage $language, InterfaceLanguageGenerator $generator): RedirectResponse
    {
        Gate::authorize('manage-ai-models');

        /** @var User $actor */
        $actor = $request->user('web');
        $generator->step($language, $actor);

        return back();
    }

    /** Correct or write one line by hand. */
    public function updateString(Request $request, InterfaceLanguage $language): RedirectResponse
    {
        Gate::authorize('manage-ai-models');

        $data = $request->validate([
            'key' => ['required', 'string', Rule::in(array_keys(InterfaceLanguages::sourceStrings()))],
            'value' => ['nullable', 'string', 'max:2000'],
        ]);

        $messages = $language->messages ?? [];
        $value = trim((string) ($data['value'] ?? ''));

        if ($value === '') {
            unset($messages[$data['key']]);
        } else {
            $messages[$data['key']] = $value;
        }

        $language->forceFill(['messages' => $messages])->save();

        return back();
    }

    /** The whole dictionary as a JSON file, to edit elsewhere. */
    public function export(InterfaceLanguage $language): StreamedResponse
    {
        Gate::authorize('manage-ai-models');

        $source = InterfaceLanguages::sourceStrings();
        $messages = $language->translations();
        $data = [];

        foreach (array_keys($source) as $key) {
            $data[$key] = $messages[$key] ?? '';
        }

        return response()->streamDownload(function () use ($data): void {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $language->code.'.json', ['Content-Type' => 'application/json']);
    }

    /** Bring back an edited JSON file: its non-empty lines replace ours. */
    public function import(Request $request, InterfaceLanguage $language): RedirectResponse
    {
        Gate::authorize('manage-ai-models');

        $request->validate([
            'file' => ['required', 'file', 'max:4096'],
        ]);

        $decoded = json_decode((string) file_get_contents((string) $request->file('file')?->getRealPath()), true);

        if (! is_array($decoded)) {
            return back()->withErrors(['file' => __('This is not a JSON translations file.')]);
        }

        $source = InterfaceLanguages::sourceStrings();
        $messages = $language->messages ?? [];
        $count = 0;

        foreach ($decoded as $key => $value) {
            if (is_string($key) && isset($source[$key]) && is_string($value) && trim($value) !== '') {
                $messages[$key] = trim($value);
                $count++;
            }
        }

        $language->forceFill(['messages' => $messages])->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':count lines imported.', ['count' => $count])]);

        return back();
    }
}

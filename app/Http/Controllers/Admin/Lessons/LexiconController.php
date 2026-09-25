<?php

namespace App\Http\Controllers\Admin\Lessons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lessons\StoreLexiconItemRequest;
use App\Http\Requests\Admin\Lessons\UpdateLexiconItemRequest;
use App\Models\LexiconItem;
use App\Models\User;
use App\Services\Content\BlockShaper;
use App\Services\Content\LexiconService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Words and expressions (CMS-06, CTRL-03, GEN-01, GEN-03, GEN-04,
 * TTS-01..03, TTS-06). LexiconItemPolicy authorizes; LexiconService writes.
 */
class LexiconController extends Controller
{
    /**
     * Search existing items to reuse in a block (CMS-06). JSON for the
     * editor's search box.
     */
    public function index(Request $request, LexiconService $lexicon, BlockShaper $shaper): JsonResponse
    {
        Gate::authorize('viewAny', LexiconItem::class);

        /** @var User $user */
        $user = $request->user();
        $kind = $request->query('kind');

        $items = $lexicon
            ->search($user, trim((string) $request->query('search', '')), is_string($kind) ? $kind : null)
            ->limit(20)
            ->get();

        $audio = $shaper->audioMap(array_values($items->flatMap(fn (LexiconItem $item): array => $item->playableTexts())->unique()->all()));

        return response()->json([
            'items' => $items->map(fn (LexiconItem $item): array => $shaper->lexiconRow($item, $audio))->values()->all(),
        ]);
    }

    public function store(StoreLexiconItemRequest $request, LexiconService $lexicon): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $item = $lexicon->create($request->itemData(), $user, $request->block());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('":text" was added.', ['text' => $item->english_text])]);

        return back();
    }

    public function update(UpdateLexiconItemRequest $request, LexiconItem $item, LexiconService $lexicon): RedirectResponse
    {
        $lexicon->update($item, $request->itemData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('":text" was saved.', ['text' => $item->english_text])]);

        return back();
    }

    /**
     * Queue the AI draft (GEN-01). The editor polls the page until the item's
     * status is done or failed (PERF-04).
     */
    public function generate(LexiconItem $item, LexiconService $lexicon): RedirectResponse
    {
        Gate::authorize('update', $item);

        $lexicon->generate($item);

        return back();
    }

    /**
     * Apply the draft, with whatever the admin edited first (GEN-03, GEN-04).
     */
    public function applyDraft(Request $request, LexiconItem $item, LexiconService $lexicon): RedirectResponse
    {
        Gate::authorize('update', $item);

        $edited = $request->validate([
            'arabic_meaning' => ['sometimes', 'nullable', 'string', 'max:500'],
            'simple_explanation' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'hotel_example' => ['sometimes', 'nullable', 'string', 'max:500'],
            'hotel_example_arabic' => ['sometimes', 'nullable', 'string', 'max:500'],
            'ipa' => ['sometimes', 'nullable', 'string', 'max:120'],
            'part_of_speech' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $lexicon->applyDraft($item, $edited);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The AI draft was applied to ":text".', ['text' => $item->english_text])]);

        return back();
    }

    /**
     * Generate the normal and slow clips (TTS-01, TTS-02).
     */
    public function audio(LexiconItem $item, LexiconService $lexicon): RedirectResponse
    {
        Gate::authorize('update', $item);

        $lexicon->generateAudio($item);

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Audio for ":text" is being generated.', ['text' => $item->english_text])]);

        return back();
    }
}

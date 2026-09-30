<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Http\Requests\Learn\StorePhrasebookItemRequest;
use App\Models\AuditLog;
use App\Models\LexiconItem;
use App\Models\PhrasebookItem;
use App\Models\User;
use App\Services\Learning\PayloadResolver;
use App\Services\Learning\ProgressService;
use App\Services\Meaning\HelperMeaningSwap;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * My Phrasebook (PHRASE-01..05, DATA-09; spec 0003 Part E).
 *
 * Personal and server-side, so it follows the learner across devices
 * (PHRASE-04). Saving is idempotent: a lexicon item is one row per (user,
 * item), a custom phrase one row per (user, normalised text). Store and
 * destroy answer JSON when asked for it, so the star toggles in place.
 */
class PhrasebookController extends Controller
{
    /** Cards in one review session: a few minutes on a phone. */
    public const DECK_SIZE = 20;

    public function __construct(
        private readonly PayloadResolver $resolver,
        private readonly ProgressService $progress,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $items = $user->phrasebookItems()
            ->with(['lexiconItem.image', 'sourceLesson'])
            ->orderByDesc('saved_at')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('employee/Phrasebook', [
            'items' => $this->entries($items),
            // Spaced review (spec 0005 §3.4): how many cards are waiting.
            'review' => [
                'due' => $user->phrasebookItems()->dueForReview()->count(),
                'url' => route('learn.phrasebook.review'),
            ],
        ]);
    }

    /**
     * Review mode (spec 0005 §3.4): today's deck of due cards, the ones the
     * learner missed first, then the oldest due, then new ones. English and
     * audio lead; the meaning appears only when the learner asks for it
     * (CTRL-01, CTRL-02).
     */
    public function review(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $deck = $user->phrasebookItems()
            ->dueForReview()
            ->with(['lexiconItem.image', 'sourceLesson'])
            ->orderByDesc('lapse_count')
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderBy('id')
            ->limit(self::DECK_SIZE)
            ->get();

        return Inertia::render('employee/PhrasebookReview', [
            'cards' => $this->entries($deck),
            'totalSaved' => $user->phrasebookItems()->count(),
        ]);
    }

    /**
     * One card answered: "I knew it" or "Practise again" (spec 0005 §3.4).
     *
     * `version` is the card's review count as the page received it. The
     * answer is recorded only when it still matches, so a retried or doubled
     * request moves the card once, never twice (PROG-04; spec 0005 §5.5); a
     * request that comes too late answers with the card's current state.
     */
    public function recordReview(Request $request, PhrasebookItem $item): JsonResponse|RedirectResponse
    {
        Gate::authorize('review', $item);

        $validated = $request->validate([
            'knew' => ['required', 'boolean'],
            'version' => ['required', 'integer', 'min:0'],
        ]);

        $recorded = (int) $validated['version'] === $item->review_count;

        if ($recorded) {
            $item->recordReview((bool) $validated['knew']);
            $this->progress->touch($item->user()->firstOrFail());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $item->id,
                'recorded' => $recorded,
                'version' => $item->review_count,
                'box' => $item->review_box,
                'dueAt' => $item->due_at?->toIso8601String(),
                'needsPractice' => $item->needsPractice(),
            ]);
        }

        return back();
    }

    /**
     * The card shape the list and the review deck share.
     *
     * @param  Collection<int, PhrasebookItem>  $items
     * @return list<array<string, mixed>>
     */
    private function entries(Collection $items): array
    {
        $texts = $items->flatMap(function (PhrasebookItem $item): array {
            $lexicon = $this->lexiconOf($item);

            return array_values(array_filter([
                $lexicon === null ? $item->custom_text : $lexicon->english_text,
                $lexicon?->hotel_example,
            ]));
        });

        $audio = $this->resolver->audioForMany($texts);
        $silent = ['normal' => null, 'slow' => null];

        $entries = array_values($items->map(function (PhrasebookItem $item) use ($audio, $silent): array {
            $lexicon = $this->lexiconOf($item);
            $text = $lexicon === null ? (string) $item->custom_text : $lexicon->english_text;

            return [
                'id' => $item->id,
                'source' => $lexicon === null ? 'custom' : 'lexicon',
                'lexiconItemId' => $lexicon?->id,
                'kind' => $lexicon?->kind->value,
                'text' => $text,
                'ipa' => $lexicon?->ipa,
                'image' => $this->resolver->media($lexicon?->image),
                'audio' => $audio[$text] ?? $silent,
                'example' => $lexicon?->hotel_example,
                'exampleAudio' => $lexicon?->hotel_example === null ? null : ($audio[$lexicon->hotel_example] ?? $silent),
                'showMeaning' => $lexicon === null ? $item->custom_arabic !== null : $lexicon->allowsShowMeaning(),
                'meaning' => $lexicon === null
                    ? ($item->custom_arabic === null ? null : ['arabic' => $item->custom_arabic, 'explanation' => null, 'exampleArabic' => null])
                    : ($lexicon->allowsShowMeaning() ? [
                        'arabic' => $lexicon->arabic_meaning,
                        'explanation' => $lexicon->simple_explanation,
                        'exampleArabic' => $lexicon->hotel_example_arabic,
                    ] : null),
                'lesson' => $item->sourceLesson === null ? null : [
                    'id' => $item->sourceLesson->id,
                    'title' => $item->sourceLesson->title,
                ],
                'savedAt' => $item->saved_at->toIso8601String(),
                'removeUrl' => route('learn.phrasebook.destroy', ['item' => $item]),
                // Spaced review state (spec 0005 §3.4).
                'mastery' => $item->review_box,
                'masteryMax' => PhrasebookItem::MAX_BOX,
                'needsPractice' => $item->needsPractice(),
                'reviewCount' => $item->review_count,
                'reviewUrl' => route('learn.phrasebook.review.store', ['item' => $item]),
            ];
        })->all());

        // The meanings in the learner's helper language (client request
        // 2026-09-30).
        $user = request()->user('web');

        return $user instanceof User ? app(HelperMeaningSwap::class)->apply($entries, $user) : $entries;
    }

    public function store(StorePhrasebookItemRequest $request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $lexiconItemId = $request->lexiconItemId();
        $text = $request->text();

        if ($lexiconItemId !== null) {
            $item = PhrasebookItem::query()->firstOrCreate(
                ['user_id' => $user->id, 'lexicon_item_id' => $lexiconItemId],
                ['saved_at' => now(), 'source_lesson_id' => $request->sourceLessonId()],
            );
        } else {
            $item = PhrasebookItem::query()->firstOrCreate(
                ['user_id' => $user->id, 'custom_hash' => PhrasebookItem::hashFor((string) $text)],
                [
                    'custom_text' => $text,
                    'custom_arabic' => $request->arabic(),
                    'saved_at' => now(),
                    'source_lesson_id' => $request->sourceLessonId(),
                ],
            );
        }

        if ($item->wasRecentlyCreated) {
            AuditLog::record($item, 'phrasebook.saved');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $item->id,
                'saved' => true,
                'created' => $item->wasRecentlyCreated,
                'removeUrl' => route('learn.phrasebook.destroy', ['item' => $item]),
            ], $item->wasRecentlyCreated ? 201 : 200);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $item->wasRecentlyCreated ? __('Saved to My Phrasebook.') : __('Already in My Phrasebook.'),
        ]);

        return back();
    }

    public function destroy(Request $request, PhrasebookItem $item): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $item);

        AuditLog::record($item, 'phrasebook.removed');

        $item->delete();

        if ($request->wantsJson()) {
            return response()->json(['id' => $item->id, 'saved' => false]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Removed from My Phrasebook.'),
        ]);

        return back();
    }

    /**
     * The saved lexicon item, or null for a custom phrase. Read through the
     * foreign key so a custom row never resolves a relation that is not there.
     */
    private function lexiconOf(PhrasebookItem $item): ?LexiconItem
    {
        return $item->lexicon_item_id === null ? null : $item->lexiconItem;
    }
}

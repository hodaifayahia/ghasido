<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Http\Requests\Learn\StorePhrasebookItemRequest;
use App\Models\AuditLog;
use App\Models\LexiconItem;
use App\Models\PhrasebookItem;
use App\Models\User;
use App\Services\Learning\PayloadResolver;
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
    public function __construct(private readonly PayloadResolver $resolver) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $items = $user->phrasebookItems()
            ->with(['lexiconItem.image', 'sourceLesson'])
            ->orderByDesc('saved_at')
            ->orderByDesc('id')
            ->get();

        $texts = $items->flatMap(function (PhrasebookItem $item): array {
            $lexicon = $this->lexiconOf($item);

            return array_values(array_filter([
                $lexicon === null ? $item->custom_text : $lexicon->english_text,
                $lexicon?->hotel_example,
            ]));
        });

        $audio = $this->resolver->audioForMany($texts);
        $silent = ['normal' => null, 'slow' => null];

        return Inertia::render('employee/Phrasebook', [
            'items' => $items->map(function (PhrasebookItem $item) use ($audio, $silent): array {
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
                ];
            })->values()->all(),
        ]);
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

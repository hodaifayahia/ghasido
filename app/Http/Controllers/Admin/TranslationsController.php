<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GenerationStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Jobs\TranslateText;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Test;
use App\Models\TextTranslation;
use App\Models\User;
use App\Services\Ai\AiLimitReached;
use App\Services\Meaning\HelperLanguages;
use App\Services\Meaning\MeaningTexts;
use App\Services\Meaning\MeaningTranslations;
use App\Services\Owner\ApiCredit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Show Meaning translations, managed by the admin (user request 2026-09-26):
 * every English text of a lesson, test or course with its meaning in one
 * helper language (Arabic, French or any the Super Admin added; client
 * request 2026-09-30), written by hand or drafted once by AI. Learners only
 * ever read them. `language` picks the helper language; Arabic by default.
 *
 * Open to whoever may edit lessons or tests; the check is repeated in every
 * action (ROLE-01, SEC-01).
 */
class TranslationsController extends Controller
{
    public function __construct(private readonly HelperLanguages $languages) {}

    private const int PER_PAGE = 30;

    public function index(Request $request): Response
    {
        $editor = $this->authorizeEditor($request);

        $scope = $this->scope($request);
        $locale = $this->locale($request);
        $state = (string) $request->query('state', 'all');
        $search = trim((string) $request->query('search', ''));

        if ($scope !== null) {
            // One piece of content: every text it shows, translated or not.
            $texts = $scope['texts'];
            $rows = TextTranslation::query()
                ->where('locale', $locale)
                ->whereIn('hash', array_map(TextTranslation::hashOf(...), $texts))
                ->get()
                ->keyBy('hash');

            $items = array_values(array_filter(
                array_map(fn (string $text): array => $this->row($text, $rows->get(TextTranslation::hashOf($text))), $texts),
                fn (array $row): bool => $this->matches($row, $state, $search),
            ));

            $pagination = ['currentPage' => 1, 'lastPage' => 1, 'total' => count($items)];
        } else {
            // Everything: the texts learners asked for first, then the rest.
            $page = TextTranslation::query()
                ->where('locale', $locale)
                ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                    ->where('source_text', 'like', '%'.addcslashes($search, '%_').'%')
                    ->orWhere('translation', 'like', '%'.addcslashes($search, '%_').'%')))
                ->when($state === 'missing', fn (Builder $q) => $q->where('status', '!=', GenerationStatus::Done->value))
                ->when($state === 'ai', fn (Builder $q) => $q->where('source', MeaningTranslations::SOURCE_AI)->where('status', GenerationStatus::Done->value))
                ->when($state === 'manual', fn (Builder $q) => $q->where('source', MeaningTranslations::SOURCE_MANUAL))
                ->orderByRaw('CASE WHEN source = ? THEN 0 ELSE 1 END', [MeaningTranslations::SOURCE_REQUESTED])
                ->orderByDesc('updated_at')
                ->paginate(self::PER_PAGE)
                ->withQueryString();

            $items = collect($page->items())
                ->map(fn (TextTranslation $translation): array => $this->row($translation->source_text, $translation))
                ->values()
                ->all();

            $pagination = ['currentPage' => $page->currentPage(), 'lastPage' => $page->lastPage(), 'total' => $page->total()];
        }

        return Inertia::render('admin/Translations', [
            'rows' => $items,
            'pagination' => $pagination,
            'scope' => $scope === null ? null : ['kind' => $scope['kind'], 'id' => $scope['id'], 'title' => $scope['title']],
            'filters' => ['state' => $state, 'search' => $search, 'language' => $locale],
            'languages' => array_map(fn (array $language): array => [
                'value' => $language['code'],
                'label' => $language['name'].($language['active'] ? '' : ' ('.__('off').')'),
                'dir' => $language['dir'],
            ], $this->languages->all()),
            'language' => ['code' => $locale, 'name' => $this->languages->name($locale), 'dir' => $this->languages->direction($locale)],
            'counts' => [
                'waiting' => TextTranslation::query()->where('locale', $locale)->where('source', MeaningTranslations::SOURCE_REQUESTED)->where('status', '!=', GenerationStatus::Done->value)->count(),
                'total' => TextTranslation::query()->where('locale', $locale)->where('status', GenerationStatus::Done->value)->count(),
            ],
            'options' => [
                'lessons' => $this->inReach(Lesson::query(), $editor)->orderBy('title')->get(['id', 'title'])->map(fn (Lesson $lesson): array => ['value' => 'lesson:'.$lesson->id, 'label' => $lesson->title])->all(),
                'tests' => $this->inReach(Test::query(), $editor)->orderBy('title')->get(['id', 'title'])->map(fn (Test $test): array => ['value' => 'test:'.$test->id, 'label' => $test->title])->all(),
                'courses' => $this->inReach(Course::query(), $editor)->orderBy('title')->get(['id', 'title'])->map(fn (Course $course): array => ['value' => 'course:'.$course->id, 'label' => $course->title])->all(),
            ],
        ]);
    }

    /** Write or correct one meaning by hand; the AI never replaces it. */
    public function save(Request $request, MeaningTranslations $translations): RedirectResponse
    {
        $admin = $this->authorizeWriter($request);

        $written = $this->writtenText($request);
        $text = TextTranslation::normalise((string) $request->input('text'));
        $translation = $this->manualRow($text, $this->locale($request));

        $translations->write($translation, $written, $admin);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Meaning saved.')]);

        return back();
    }

    /**
     * Ask the AI for the texts that have no meaning yet, in one piece of
     * content or, with no content chosen, the ones learners asked for.
     */
    public function generate(Request $request, MeaningTranslations $translations): RedirectResponse
    {
        $admin = $this->authorizeWriter($request);
        $scope = $this->scope($request);
        $locale = $this->locale($request);

        $texts = $scope !== null
            ? $scope['texts']
            : TextTranslation::query()->where('locale', $locale)->where('source', MeaningTranslations::SOURCE_REQUESTED)->pluck('source_text')->all();

        $queued = $translations->queue(array_values(array_map(strval(...), $texts)), $admin, [$locale]);

        Inertia::flash('toast', [
            'type' => $queued > 0 ? 'success' : 'info',
            'message' => $queued > 0
                ? trans_choice(':count meaning is being drafted by AI.|:count meanings are being drafted by AI.', $queued, ['count' => $queued])
                : __('Every text here already has a meaning.'),
        ]);

        return back();
    }

    /**
     * The meaning of the texts an editor is typing (client request
     * 2026-09-29: a "Translate meaning to Arabic" button above every English
     * field of the test and lesson builders). A read: POST only so a long
     * batch of field texts fits in the body.
     */
    public function lookup(Request $request): JsonResponse
    {
        $this->authorizeEditor($request);

        $data = $request->validate([
            'texts' => ['required', 'array', 'max:200'],
            'texts.*' => ['nullable', 'string', 'max:'.TextTranslation::MAX_LENGTH],
        ]);

        /** @var list<string|null> $texts */
        $texts = array_values($data['texts']);
        $normalised = array_map(fn (?string $text): string => TextTranslation::normalise((string) $text), $texts);

        $rows = TextTranslation::query()
            ->where('locale', $this->locale($request))
            ->whereIn('hash', array_map(TextTranslation::hashOf(...), array_filter($normalised, fn (string $text): bool => $text !== '')))
            ->get()
            ->keyBy('hash');

        return response()->json([
            'items' => array_map(
                fn (string $text): array => $this->row($text, $text === '' ? null : $rows->get(TextTranslation::hashOf($text))),
                $normalised,
            ),
        ]);
    }

    /**
     * Draft one field's meaning with AI. It runs right after the response
     * (a server with no queue worker still finishes it) and the button polls
     * lookup meanwhile (PERF-04). A meaning written by hand is never
     * replaced: the call returns it unchanged.
     */
    public function draft(Request $request): JsonResponse
    {
        $admin = $this->authorizeWriter($request);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:'.TextTranslation::MAX_LENGTH],
        ]);

        $text = TextTranslation::normalise((string) $data['text']);

        abort_if($text === '' || preg_match('/\p{L}/u', $text) !== 1, 422, __('There is nothing to translate.'));

        $translation = MeaningTranslations::find($text, $this->locale($request));

        if ($translation->exists && $translation->source === MeaningTranslations::SOURCE_MANUAL) {
            return response()->json(['item' => $this->row($text, $translation)]);
        }

        // A draft already on its way is not started twice (security audit
        // 2026-11-08: repeated clicks each spent an AI call).
        if ($translation->exists && in_array($translation->status, [GenerationStatus::Pending, GenerationStatus::Running], true)) {
            return response()->json(['item' => $this->row($text, $translation)], 202);
        }

        // The owner's AI credit is checked before anything is spent (D7a).
        try {
            app(ApiCredit::class)->assertCapabilities('ai');
        } catch (AiLimitReached $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        }

        $translation->fill([
            'source_text' => $translation->source_text ?? $text,
            'status' => GenerationStatus::Pending,
            'source' => MeaningTranslations::SOURCE_AI,
            'failed_reason' => null,
            'requested_by' => $translation->requested_by ?? $admin->id,
        ])->save();

        // Straight to the bus: bypasses the unique lock a queued draft of
        // the same row may still hold on a server with no worker. The job
        // meters the call itself (API-03).
        Bus::dispatchAfterResponse(new TranslateText($translation->id));

        return response()->json(['item' => $this->row($text, $translation)], 202);
    }

    /** Save one field's meaning as written by hand (JSON twin of save()). */
    public function write(Request $request, MeaningTranslations $translations): JsonResponse
    {
        $admin = $this->authorizeWriter($request);

        $written = $this->writtenText($request);
        $text = TextTranslation::normalise((string) $request->input('text'));

        abort_if($text === '', 422, __('There is nothing to translate.'));

        $translation = $this->manualRow($text, $this->locale($request));

        $translations->write($translation, $written, $admin);

        return response()->json(['item' => $this->row($text, $translation)]);
    }

    /**
     * The helper language being edited: `language`, Arabic by default. Any
     * language on the list, switched off or not, so its translations can
     * be prepared before it goes live.
     */
    private function locale(Request $request): string
    {
        $code = $request->input('language');

        return is_string($code) && $this->languages->find($code) !== null ? $code : 'ar';
    }

    /**
     * The hand-written meaning: `translation`, or `arabic` from the builders'
     * Arabic field button.
     */
    private function writtenText(Request $request): string
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:'.TextTranslation::MAX_LENGTH],
            'translation' => ['required_without:arabic', 'nullable', 'string', 'max:3000'],
            'arabic' => ['required_without:translation', 'nullable', 'string', 'max:3000'],
        ]);

        return (string) ($data['translation'] ?? $data['arabic'] ?? '');
    }

    private function manualRow(string $text, string $locale): TextTranslation
    {
        $translation = MeaningTranslations::find($text, $locale);

        if (! $translation->exists) {
            $translation->fill(['status' => GenerationStatus::Pending, 'source' => MeaningTranslations::SOURCE_MANUAL]);
        }

        return $translation;
    }

    private function authorizeEditor(Request $request): User
    {
        $user = $request->user('web');

        abort_unless(
            $user instanceof User && ($user->can(Permission::LessonsManage->value) || $user->can(Permission::TestsManage->value)),
            403,
        );

        return $user;
    }

    /**
     * Writing a meaning changes it for every hotel that shows the same
     * English text (rows are keyed by the text, not by a hotel), so only
     * platform staff and the Super Admin write them; a hotel-bound editor
     * reads (ROLE-02; security audit pass 3).
     */
    private function authorizeWriter(Request $request): User
    {
        $user = $this->authorizeEditor($request);

        abort_unless(
            $user->hotel_id === null || $user->hasRole(Role::SuperAdmin->value),
            403,
            __('Only platform staff can change shared translations.'),
        );

        return $user;
    }

    /**
     * Below the Super Admin, only shared content and the editor's own
     * hotel's (security audit 2026-11-08, ROLE-02).
     *
     * @template TModel of Lesson|Test|Course
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function inReach(Builder $query, User $editor): Builder
    {
        if ($editor->hasRole(Role::SuperAdmin->value)) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($editor): void {
            $inner->whereNull('hotel_id');

            if ($editor->hotel_id !== null) {
                $inner->orWhere('hotel_id', $editor->hotel_id);
            }
        });
    }

    /**
     * The lesson, test or course chosen with `?content=lesson:12`.
     *
     * @return array{kind: string, id: int, title: string, texts: list<string>}|null
     */
    private function scope(Request $request): ?array
    {
        $value = (string) $request->input('content', '');

        if (preg_match('/^(lesson|test|course):(\d+)$/', $value, $m) !== 1) {
            return null;
        }

        $id = (int) $m[2];

        // Security audit 2026-11-08: the chosen content must be one the
        // editor may change, shared or their own hotel's (ROLE-02, SEC-01).
        if ($m[1] === 'lesson') {
            $lesson = Lesson::query()->findOrFail($id);
            Gate::authorize('update', $lesson);

            return ['kind' => 'lesson', 'id' => $id, 'title' => $lesson->title, 'texts' => MeaningTexts::forLesson($lesson)];
        }

        if ($m[1] === 'test') {
            $test = Test::query()->findOrFail($id);
            Gate::authorize('update', $test);

            return ['kind' => 'test', 'id' => $id, 'title' => $test->title, 'texts' => MeaningTexts::forTest($test)];
        }

        $course = Course::query()->findOrFail($id);
        Gate::authorize('update', $course);

        return ['kind' => 'course', 'id' => $id, 'title' => $course->title, 'texts' => MeaningTexts::forCourse($course)];
    }

    /**
     * @return array{id: int|null, text: string, translation: string|null, arabic: string|null, state: string, updatedAt: string|null}
     */
    private function row(string $text, ?TextTranslation $translation): array
    {
        $state = match (true) {
            $translation === null, $translation->source === MeaningTranslations::SOURCE_REQUESTED && $translation->status !== GenerationStatus::Done => 'missing',
            $translation->status === GenerationStatus::Done => $translation->source === MeaningTranslations::SOURCE_MANUAL ? 'manual' : 'ai',
            $translation->status === GenerationStatus::Failed => 'failed',
            default => 'drafting',
        };

        return [
            'id' => $translation?->id,
            'text' => $text,
            'translation' => $translation?->translation,
            // The builders' Arabic field button reads this name.
            'arabic' => $translation?->translation,
            'state' => $state,
            'updatedAt' => $translation?->updated_at?->diffForHumans(),
        ];
    }

    /**
     * @param  array{id: int|null, text: string, translation: string|null, arabic: string|null, state: string, updatedAt: string|null}  $row
     */
    private function matches(array $row, string $state, string $search): bool
    {
        $stateOk = match ($state) {
            'missing' => in_array($row['state'], ['missing', 'failed', 'drafting'], true),
            'ai', 'manual' => $row['state'] === $state,
            default => true,
        };

        return $stateOk && ($search === '' || str_contains(mb_strtolower($row['text'].' '.$row['translation']), mb_strtolower($search)));
    }
}

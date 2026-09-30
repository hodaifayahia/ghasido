<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GenerationStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Jobs\TranslateText;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Test;
use App\Models\TextTranslation;
use App\Models\User;
use App\Services\Ai\AiLimitReached;
use App\Services\Meaning\MeaningTexts;
use App\Services\Meaning\MeaningTranslations;
use App\Services\Owner\ApiCredit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Show Meaning translations, managed by the admin (user request 2026-09-26):
 * every English text of a lesson, test or course with its Arabic, written by
 * hand or drafted once by AI. Learners only ever read them.
 *
 * Open to whoever may edit lessons or tests; the check is repeated in every
 * action (ROLE-01, SEC-01).
 */
class TranslationsController extends Controller
{
    private const int PER_PAGE = 30;

    public function index(Request $request): Response
    {
        $this->authorizeEditor($request);

        $scope = $this->scope($request);
        $state = (string) $request->query('state', 'all');
        $search = trim((string) $request->query('search', ''));

        if ($scope !== null) {
            // One piece of content: every text it shows, translated or not.
            $texts = $scope['texts'];
            $rows = TextTranslation::query()
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
                ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                    ->where('source_text', 'like', '%'.addcslashes($search, '%_').'%')
                    ->orWhere('arabic', 'like', '%'.addcslashes($search, '%_').'%')))
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
            'filters' => ['state' => $state, 'search' => $search],
            'counts' => [
                'waiting' => TextTranslation::query()->where('source', MeaningTranslations::SOURCE_REQUESTED)->where('status', '!=', GenerationStatus::Done->value)->count(),
                'total' => TextTranslation::query()->where('status', GenerationStatus::Done->value)->count(),
            ],
            'options' => [
                'lessons' => Lesson::query()->orderBy('title')->get(['id', 'title'])->map(fn (Lesson $lesson): array => ['value' => 'lesson:'.$lesson->id, 'label' => $lesson->title])->all(),
                'tests' => Test::query()->orderBy('title')->get(['id', 'title'])->map(fn (Test $test): array => ['value' => 'test:'.$test->id, 'label' => $test->title])->all(),
                'courses' => Course::query()->orderBy('title')->get(['id', 'title'])->map(fn (Course $course): array => ['value' => 'course:'.$course->id, 'label' => $course->title])->all(),
            ],
        ]);
    }

    /** Write or correct one meaning by hand; the AI never replaces it. */
    public function save(Request $request, MeaningTranslations $translations): RedirectResponse
    {
        $admin = $this->authorizeEditor($request);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:'.TextTranslation::MAX_LENGTH],
            'arabic' => ['required', 'string', 'max:3000'],
        ]);

        $text = TextTranslation::normalise((string) $data['text']);
        $translation = TextTranslation::query()->firstOrNew(
            ['hash' => TextTranslation::hashOf($text)],
            ['source_text' => $text, 'status' => GenerationStatus::Pending, 'source' => MeaningTranslations::SOURCE_MANUAL],
        );

        $translations->write($translation, (string) $data['arabic'], $admin);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Meaning saved.')]);

        return back();
    }

    /**
     * Ask the AI for the texts that have no meaning yet, in one piece of
     * content or, with no content chosen, the ones learners asked for.
     */
    public function generate(Request $request, MeaningTranslations $translations): RedirectResponse
    {
        $admin = $this->authorizeEditor($request);
        $scope = $this->scope($request);

        $texts = $scope !== null
            ? $scope['texts']
            : TextTranslation::query()->where('source', MeaningTranslations::SOURCE_REQUESTED)->pluck('source_text')->all();

        $queued = $translations->queue(array_values(array_map(strval(...), $texts)), $admin);

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
        $admin = $this->authorizeEditor($request);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:'.TextTranslation::MAX_LENGTH],
        ]);

        $text = TextTranslation::normalise((string) $data['text']);

        abort_if($text === '' || preg_match('/\p{L}/u', $text) !== 1, 422, __('There is nothing to translate.'));

        $translation = TextTranslation::query()->firstOrNew(['hash' => TextTranslation::hashOf($text)]);

        if ($translation->exists && $translation->source === MeaningTranslations::SOURCE_MANUAL) {
            return response()->json(['item' => $this->row($text, $translation)]);
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
        $admin = $this->authorizeEditor($request);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:'.TextTranslation::MAX_LENGTH],
            'arabic' => ['required', 'string', 'max:3000'],
        ]);

        $text = TextTranslation::normalise((string) $data['text']);

        abort_if($text === '', 422, __('There is nothing to translate.'));

        $translation = TextTranslation::query()->firstOrNew(
            ['hash' => TextTranslation::hashOf($text)],
            ['source_text' => $text, 'status' => GenerationStatus::Pending, 'source' => MeaningTranslations::SOURCE_MANUAL],
        );

        $translations->write($translation, (string) $data['arabic'], $admin);

        return response()->json(['item' => $this->row($text, $translation)]);
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

        if ($m[1] === 'lesson') {
            $lesson = Lesson::query()->findOrFail($id);

            return ['kind' => 'lesson', 'id' => $id, 'title' => $lesson->title, 'texts' => MeaningTexts::forLesson($lesson)];
        }

        if ($m[1] === 'test') {
            $test = Test::query()->findOrFail($id);

            return ['kind' => 'test', 'id' => $id, 'title' => $test->title, 'texts' => MeaningTexts::forTest($test)];
        }

        $course = Course::query()->findOrFail($id);

        return ['kind' => 'course', 'id' => $id, 'title' => $course->title, 'texts' => MeaningTexts::forCourse($course)];
    }

    /**
     * @return array{id: int|null, text: string, arabic: string|null, state: string, updatedAt: string|null}
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
            'arabic' => $translation?->arabic,
            'state' => $state,
            'updatedAt' => $translation?->updated_at?->diffForHumans(),
        ];
    }

    /**
     * @param  array{id: int|null, text: string, arabic: string|null, state: string, updatedAt: string|null}  $row
     */
    private function matches(array $row, string $state, string $search): bool
    {
        $stateOk = match ($state) {
            'missing' => in_array($row['state'], ['missing', 'failed', 'drafting'], true),
            'ai', 'manual' => $row['state'] === $state,
            default => true,
        };

        return $stateOk && ($search === '' || str_contains(mb_strtolower($row['text'].' '.$row['arabic']), mb_strtolower($search)));
    }
}

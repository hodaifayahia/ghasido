<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GenerationStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Test;
use App\Models\TextTranslation;
use App\Models\User;
use App\Services\Meaning\MeaningTexts;
use App\Services\Meaning\MeaningTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    /**
     * Write or correct one meaning by hand; the AI never replaces it. The
     * builders' Translation button (user request 2026-09-26) calls this as
     * JSON, so a save never reloads the editor and loses unsaved typing.
     */
    public function save(Request $request, MeaningTranslations $translations): RedirectResponse|JsonResponse
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

        if ($request->expectsJson() && ! $request->hasHeader('X-Inertia')) {
            return response()->json($this->row($text, $translation->refresh()));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Meaning saved.')]);

        return back();
    }

    /**
     * The current meaning of each text a builder shows, for its Translation
     * buttons (user request 2026-09-26). Read-only: an unknown text is only
     * reported missing, never queued for the AI or noted as requested.
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
        $rows = TextTranslation::query()
            ->whereIn('hash', array_map(fn (?string $text): string => TextTranslation::hashOf((string) $text), $texts))
            ->get()
            ->keyBy('hash');

        return response()->json([
            'items' => array_map(
                fn (?string $text): array => $this->row((string) $text, $rows->get(TextTranslation::hashOf((string) $text))),
                $texts,
            ),
        ]);
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

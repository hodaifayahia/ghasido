<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityType;
use App\Enums\ContentStatus;
use App\Enums\MediaLibrary;
use App\Enums\ResultsVisibility;
use App\Enums\TestQuestionSkill;
use App\Enums\TestType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tests\GenerateTestQuestionsRequest;
use App\Http\Requests\Admin\Tests\ImportTestQuestionsRequest;
use App\Http\Requests\Admin\Tests\StoreTestQuestionRequest;
use App\Http\Requests\Admin\Tests\StoreTestRequest;
use App\Http\Requests\Admin\Tests\UpdateTestQuestionMediaRequest;
use App\Http\Requests\Admin\Tests\UpdateTestQuestionRequest;
use App\Http\Requests\Admin\Tests\UpdateTestRequest;
use App\Models\ActivityPlacement;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\MediaAsset;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Content\BlockShaper;
use App\Services\Content\MediaService;
use App\Services\Tests\QuestionImport;
use App\Services\Tests\TestAudio;
use App\Services\Tests\TestQuestionGenerator;
use App\Services\Tests\TestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Super Admin Pre-test & Post-test builder (TEST-01..10, TSTM-01..05).
 *
 * The screen is backed by the same Test/Activity rows used by the learner
 * runner. That keeps the assessment the admin edits and the assessment the
 * employee sits in one source of truth.
 */
class TestsController extends Controller
{
    /**
     * Keep compatibility with any already-loaded route definition that still
     * references the controller as an invokable action.
     */
    public function __invoke(Request $request, MediaService $media): Response
    {
        return $this->index($request, $media);
    }

    public function index(Request $request, MediaService $media): Response
    {
        Gate::authorize('viewAny', Test::class);

        /** @var User $viewer */
        $viewer = $request->user();
        $tests = $this->query($request, $viewer)->get();
        $requestedId = (int) $request->query('test', 0);
        $selected = $requestedId > 0
            ? Test::query()->with(['department', 'hotel', 'questions.activity'])->find($requestedId)
            : null;

        if ($selected !== null) {
            Gate::authorize('view', $selected);
        }

        // The page is the test library and the builder only: the old Question
        // Bank / Results & Analytics / Settings tabs were removed at the
        // client's request (2026-09-29). Results live in Reports & Export.
        return Inertia::render('admin/Tests', [
            'builderOpen' => $selected !== null,
            'stats' => $this->stats($viewer),
            'list' => $this->listPayload($request, $tests, $viewer),
            'editor' => $this->editor($selected, $request, $viewer),
            'preview' => $this->preview($selected),
            'media' => $this->media($viewer, $media),
            'results' => $this->results($selected, $viewer),
        ]);
    }

    public function store(StoreTestRequest $request, TestService $tests): RedirectResponse
    {
        Gate::authorize('create', Test::class);

        /** @var User $actor */
        $actor = $request->user();
        $test = $tests->create($request->testData(), $actor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':title was created as a draft.', ['title' => $test->title]),
        ]);

        return to_route('tests', ['test' => $test->id]);
    }

    public function update(UpdateTestRequest $request, Test $test, TestService $tests): RedirectResponse
    {
        Gate::authorize('update', $test);

        /** @var User $actor */
        $actor = $request->user();
        $tests->update($test, $request->testData(), $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test saved.')]);

        return back();
    }

    public function publish(Request $request, Test $test, TestService $tests): RedirectResponse
    {
        Gate::authorize('publish', $test);

        /** @var User $actor */
        $actor = $request->user();
        $tests->publish($test, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':title is published.', ['title' => $test->title])]);

        return back();
    }

    /**
     * Delete a test that nobody has sat yet (client request 2026-09-29).
     *
     * A test with any sitting is refused, never cascaded: its attempts hold
     * the learners' answers, which are research data and are never deleted
     * (DATA-10). `test_attempts.test_id` is also `restrictOnDelete`, and the
     * content model has no archived state for tests, so refusing is the only
     * safe answer. The question activities stay: they are versioned rows a
     * lesson may also use (PRAC-05, DATA-11); only this test's placements go.
     */
    public function destroy(Test $test, TestService $tests): RedirectResponse
    {
        Gate::authorize('delete', $test);

        $sittings = $test->attempts()->count();

        if ($sittings > 0) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => trans_choice(
                    ':title cannot be deleted: :count employee has already taken it, and their answers are kept.|:title cannot be deleted: :count employees have already taken it, and their answers are kept.',
                    $sittings,
                    ['title' => $test->title, 'count' => $sittings],
                ),
            ]);

            return back();
        }

        $title = $test->title;
        $tests->delete($test);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':title was deleted.', ['title' => $title])]);

        return to_route('tests');
    }

    public function storeQuestion(StoreTestQuestionRequest $request, Test $test, TestService $tests): RedirectResponse
    {
        Gate::authorize('update', $test);

        /** @var User $actor */
        $actor = $request->user();
        if ($request->isActivity()) {
            $tests->addActivity($test, $request->activityData(), $actor);
        } else {
            $tests->addQuestion($test, $request->questionData(), $actor);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question added.')]);

        return back();
    }

    /**
     * Import questions from a CSV file or pasted rows (client request
     * 2026-09-26): every row is checked first, and nothing is saved while
     * any row has a problem.
     */
    public function importQuestions(ImportTestQuestionsRequest $request, Test $test, TestService $tests, QuestionImport $import): RedirectResponse
    {
        Gate::authorize('update', $test);

        $parsed = $import->parse($request->content());

        if ($parsed['errors'] !== []) {
            // One key per problem, so the dialog can list every line.
            $messages = [];

            foreach ($parsed['errors'] as $index => $error) {
                $messages['import.'.$index] = $error;
            }

            throw ValidationException::withMessages($messages);
        }

        /** @var User $actor */
        $actor = $request->user();
        $count = $tests->importQuestions($test, $parsed['questions'], $actor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice(':count question imported.|:count questions imported.', $count, ['count' => $count]),
        ]);

        return back();
    }

    /** The CSV template for the import dialog. */
    public function importTemplate(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            echo QuestionImport::template();
        }, 'ghasido-test-questions-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function updateQuestion(UpdateTestQuestionRequest $request, Test $test, ActivityPlacement $placement, TestService $tests): RedirectResponse
    {
        Gate::authorize('update', $test);
        abort_unless($placement->placeable_type === $test->getMorphClass() && (int) $placement->placeable_id === (int) $test->id, 404);

        if ($request->isActivity()) {
            $tests->updateActivity($placement, $request->activityData());
        } else {
            $tests->updateQuestion($placement, $request->questionData());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question saved as a new version.')]);

        return back();
    }

    public function updateQuestionMedia(UpdateTestQuestionMediaRequest $request, Test $test, ActivityPlacement $placement, TestService $tests): RedirectResponse
    {
        Gate::authorize('update', $test);
        abort_unless($placement->placeable_type === $test->getMorphClass() && (int) $placement->placeable_id === (int) $test->id, 404);

        /** @var User $actor */
        $actor = $request->user();
        $data = $request->mediaData();
        $tests->attachMedia($placement, $data['kind'], $data['media_id'], $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question media saved.')]);

        return back();
    }

    /**
     * Queue an AI draft of questions for this test (GEN-01, GEN-03, TSTM-03).
     */
    public function generateQuestions(GenerateTestQuestionsRequest $request, Test $test, TestQuestionGenerator $generator): RedirectResponse
    {
        Gate::authorize('update', $test);

        /** @var User $actor */
        $actor = $request->user();
        $data = $request->generationData();
        $generator->request($test, $actor, $data);

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => $data['paired']
                ? __('Generating paired questions from the Pre-test. They arrive as drafts.')
                : __('Generating questions with AI. They arrive as drafts for your review.'),
        ]);

        return back();
    }

    /**
     * Replace the last AI batch with a fresh draft (GEN-04).
     */
    public function regenerateQuestions(Request $request, Test $test, TestQuestionGenerator $generator): RedirectResponse
    {
        Gate::authorize('update', $test);

        /** @var User $actor */
        $actor = $request->user();
        $generator->regenerate($test, $actor);

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Regenerating the AI questions.')]);

        return back();
    }

    /**
     * Queue stored audio for every listening question of the test (TTS-01).
     */
    public function generateAudio(Test $test, TestAudio $audio): RedirectResponse
    {
        Gate::authorize('update', $test);

        $count = $audio->generateForTest($test);
        AuditLog::record($test, 'test.audio.requested', ['scripts' => $count]);

        Inertia::flash('toast', [
            'type' => $count > 0 ? 'info' : 'warning',
            'message' => $count > 0
                ? trans_choice('Generating audio for :count listening script.|Generating audio for :count listening scripts.', $count, ['count' => $count])
                : __('No question on this test has a listening script.'),
        ]);

        return back();
    }

    public function generateQuestionAudio(Test $test, ActivityPlacement $placement, TestAudio $audio): RedirectResponse
    {
        Gate::authorize('update', $test);
        $this->assertOnTest($test, $placement);

        $count = $audio->generate($placement->activity()->firstOrFail());

        Inertia::flash('toast', [
            'type' => $count > 0 ? 'info' : 'warning',
            'message' => $count > 0 ? __('Generating the question audio.') : __('This question has no listening script.'),
        ]);

        return back();
    }

    /**
     * Release one AI draft to learners without publishing the whole test
     * again (GEN-03).
     */
    public function releaseQuestion(Test $test, ActivityPlacement $placement, TestService $tests): RedirectResponse
    {
        Gate::authorize('update', $test);
        $this->assertOnTest($test, $placement);

        $tests->release($placement);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question approved.')]);

        return back();
    }

    /**
     * Put the test's questions in a new order (client request 2026-09-29).
     */
    public function reorderQuestions(Request $request, Test $test, TestService $tests): RedirectResponse
    {
        Gate::authorize('update', $test);

        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer', 'distinct'],
        ]);

        try {
            $tests->reorderQuestions($test, array_map('intval', $data['order']));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['order' => $exception->getMessage()]);
        }

        return back();
    }

    public function destroyQuestion(Request $request, Test $test, ActivityPlacement $placement, TestService $tests): RedirectResponse
    {
        Gate::authorize('update', $test);
        abort_unless($placement->placeable_type === $test->getMorphClass() && (int) $placement->placeable_id === (int) $test->id, 404);

        $tests->removeQuestion($placement, $test);

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Question removed from the test.')]);

        return back();
    }

    /**
     * @return Builder<Test>
     */
    private function query(Request $request, User $viewer): Builder
    {
        $query = Test::query()
            ->with(['department:id,name', 'hotel:id,name'])
            ->withCount(['questions', 'attempts'])
            ->orderBy('id');
        $this->scopeToReach($query, $viewer);
        $term = trim((string) $request->query('search', ''));

        if ($term !== '') {
            $query->where('title', 'like', '%'.addcslashes($term, '%_\\').'%');
        }

        if (($type = (string) $request->query('type', 'all-types')) !== 'all-types') {
            $query->where('type', $type);
        }

        if (($department = (int) $request->query('department', 0)) > 0) {
            $query->where('department_id', $department);
        }

        if (($hotel = (int) $request->query('hotel', 0)) > 0) {
            $query->where('hotel_id', $hotel);
        } elseif ((string) $request->query('hotel', 'all-hotels') === 'shared') {
            $query->whereNull('hotel_id');
        }

        return $query;
    }

    /**
     * @param  Collection<int, Test>  $tests
     * @return array<string, mixed>
     */
    private function listPayload(Request $request, Collection $tests, User $viewer): array
    {
        $departments = Department::query()->visibleTo($viewer)->active()->orderBy('position')->orderBy('name')->get(['id', 'name']);
        $hotels = Hotel::query()->notArchived()->when(! $viewer->hasRole('super_admin'), fn (Builder $query) => $query->whereKey($viewer->hotel_id ?? 0))->orderBy('name')->get(['id', 'name']);

        $firstDepartment = $departments->first();
        $defaultDepartmentId = $firstDepartment === null ? '' : (string) $firstDepartment->id;

        return [
            'search' => (string) $request->query('search', ''),
            'hotel' => (string) $request->query('hotel', 'all-hotels'),
            'department' => (string) $request->query('department', 'all-departments'),
            'type' => (string) $request->query('type', 'all-types'),
            'hotels' => [
                ['value' => 'all-hotels', 'label' => __('All Hotels')],
                ['value' => 'shared', 'label' => __('Shared Across Hotels')],
                ...$hotels->map(fn (Hotel $hotel): array => ['value' => (string) $hotel->id, 'label' => $hotel->name])->all(),
            ],
            'departments' => [
                ['value' => 'all-departments', 'label' => __('All Departments')],
                ...$departments->map(fn (Department $department): array => ['value' => (string) $department->id, 'label' => $department->name])->all(),
            ],
            'types' => [
                ['value' => 'all-types', 'label' => __('All Types')],
                ['value' => TestType::Pre->value, 'label' => TestType::Pre->label()],
                ['value' => TestType::Post->value, 'label' => TestType::Post->label()],
            ],
            'items' => $tests->map(fn (Test $test): array => $this->listItem($test))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listItem(Test $test): array
    {
        $minutes = $this->timeLimitMinutes($test);
        $hotel = $test->hotel;

        return [
            'id' => (string) $test->id,
            'title' => $test->title,
            'department' => $test->department->name,
            'hotel' => $hotel === null ? __('All Hotels') : $hotel->name,
            'meta' => __(':questions questions · :minutes', ['questions' => (int) $test->questions_count, 'minutes' => $minutes === null ? __('No time limit') : $minutes.' min']),
            'type' => $test->type->value,
            'questionCount' => (int) $test->questions_count,
            // Sittings (any status): a test with any cannot be deleted (DATA-10).
            'attemptCount' => (int) $test->attempts_count,
            'timeLimit' => $minutes,
            'status' => $test->status === ContentStatus::Published ? 'active' : 'draft',
            'crop' => $this->thumbCrop(((int) $test->id) % 7),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stats(User $viewer): array
    {
        $items = $this->scopeToReach(Test::query()->withCount('questions'), $viewer)->get();
        $active = $items->where('status', ContentStatus::Published)->count();

        return [
            ['key' => 'totalTests', 'value' => $items->count(), 'label' => __('Total Tests'), 'detail' => __('Pre + Post')],
            ['key' => 'activeTests', 'value' => $active, 'label' => __('Active Tests'), 'detail' => __('Ready for employees')],
            ['key' => 'draftTests', 'value' => $items->count() - $active, 'label' => __('Draft Tests'), 'detail' => __('Still in progress')],
            ['key' => 'questions', 'value' => (int) $items->sum('questions_count'), 'label' => __('Question Items'), 'detail' => __('Across the library')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function editor(?Test $selected, Request $request, User $viewer): array
    {
        $departments = Department::query()->visibleTo($viewer)->active()->orderBy('position')->orderBy('name')->get(['id', 'name']);
        $firstDepartment = $departments->first();
        $defaultDepartmentId = $firstDepartment === null ? '' : (string) $firstDepartment->id;
        $type = $selected?->type->value ?? (string) $request->query('newType', TestType::Pre->value);
        $settings = $selected === null ? [] : ($selected->settings ?? []);
        $description = is_array($selected?->intro) ? (string) ($selected->intro['description'] ?? '') : '';
        $requestedDepartment = $request->query('newDepartment', $defaultDepartmentId);
        $newDepartment = is_scalar($requestedDepartment) ? (string) $requestedDepartment : $defaultDepartmentId;

        return [
            'id' => $selected?->id,
            'updateUrl' => $selected?->id === null ? null : route('tests.update', ['test' => $selected]),
            'publishUrl' => $selected?->id === null ? null : route('tests.publish', ['test' => $selected]),
            'questionStoreUrl' => $selected?->id === null ? null : route('tests.questions.store', ['test' => $selected]),
            'title' => $selected === null ? (string) $request->query('newTitle', __('New Test')) : $selected->title,
            'type' => $type,
            'typeOptions' => [
                ['value' => TestType::Pre->value, 'label' => TestType::Pre->label()],
                ['value' => TestType::Post->value, 'label' => TestType::Post->label()],
            ],
            'department' => $selected === null ? $newDepartment : (string) $selected->department_id,
            'departments' => $departments->map(fn (Department $department): array => ['value' => (string) $department->id, 'label' => $department->name])->values()->all(),
            'hotel' => (string) ($selected === null ? '' : $selected->hotel_id),
            'timeLimit' => (string) (($settings['time_limit_seconds'] ?? null) ? (int) round(((int) $settings['time_limit_seconds']) / 60) : ''),
            'questionCount' => (string) ($selected?->questions->count() ?? 0),
            'attemptCount' => $selected === null ? 0 : $selected->attempts()->count(),
            'description' => $description,
            'descriptionCount' => strlen($description).'/300',
            // Exactly the client's ten question types, the same ten the
            // lesson activity chooser offers (client report 2026-09-29).
            'kinds' => $this->kinds(),
            'activeKind' => ActivityType::MultipleChoice->value,
            'questionKinds' => $this->kinds(),
            'ai' => $this->aiPanel($selected),
            'questions' => $selected === null ? [] : $selected->questions->map(fn (ActivityPlacement $placement, int $index): array => $this->question($placement, $index + 1))->values()->all(),
            'settings' => [
                'shuffle_questions' => (bool) ($settings['shuffle_questions'] ?? false),
                'shuffle_options' => (bool) ($settings['shuffle_options'] ?? false),
                'single_attempt' => (bool) ($settings['single_attempt'] ?? false),
                'results_visibility' => (string) ($settings['results_visibility'] ?? ResultsVisibility::Hidden->value),
                'show_answers' => (bool) ($settings['show_answers'] ?? false),
                'motivational_message' => (bool) ($settings['motivational_message'] ?? false),
                'show_meaning' => (bool) ($settings['show_meaning'] ?? true),
                'passMark' => (string) ($settings['pass_score'] ?? ''),
            ],
            'urls' => [
                'update' => $selected === null ? null : route('tests.update', $selected),
                'publish' => $selected === null ? null : route('tests.publish', $selected),
                'storeQuestion' => $selected === null ? null : route('tests.questions.store', $selected),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function question(ActivityPlacement $placement, int $index): array
    {
        $activity = $placement->activity;
        $item = $activity?->items()[0] ?? [];
        $kind = $activity === null ? 'multiple_choice' : TestService::kindFor($activity->type);
        $options = is_array($item['options'] ?? null) ? $item['options'] : [];
        $correct = (string) ($item['correct'] ?? '');
        $storedMedia = is_array($item['media'] ?? null) ? $item['media'] : [];
        $media = [];

        foreach (['image', 'audio', 'video'] as $mediaKind) {
            // The shared question editor stores the media on the item
            // itself (`image`, `audio`, `video`); older questions kept it
            // in `media` (client report 2026-09-29: an uploaded photo showed
            // the default one).
            $mediaId = $item[$mediaKind] ?? $storedMedia[$mediaKind] ?? null;

            if (is_int($mediaId) || (is_string($mediaId) && ctype_digit($mediaId))) {
                $asset = MediaAsset::query()->find((int) $mediaId);
                $media[$mediaKind] = $asset === null ? null : [
                    'id' => (string) $asset->id,
                    'label' => $asset->label ?? $asset->original_name ?? ucfirst($mediaKind),
                    'url' => $asset->url(),
                    'thumbUrl' => $asset->variantUrl('thumb'),
                    'alt' => $asset->alt_text ?? '',
                ];
            } else {
                $media[$mediaKind] = null;
            }
        }

        return [
            'id' => $placement->id,
            'index' => $index,
            'kind' => $kind,
            'text' => (string) ($item['question'] ?? $activity->prompt),
            'imageCrop' => $this->thumbCrop($index),
            'options' => array_values(array_map(static fn (array $option): array => [
                'id' => (string) ($option['id'] ?? ''),
                'text' => (string) ($option['text'] ?? $option['label'] ?? ''),
                'correct' => (string) ($option['id'] ?? '') === $correct,
            ], $options)),
            'media' => $media,
            'typeLabel' => $activity?->type->builderLabel() ?? __('Question'),
            // What the shared activity editor opens with: the activity, and
            // the media and stored audio its item names (MED-02, TTS-02).
            'activity' => $activity === null ? null : app(BlockShaper::class)->activityRow($activity, $placement),
            'mediaMap' => $activity === null ? (object) [] : (object) app(BlockShaper::class)->mediaMap(app(BlockShaper::class)->mediaIdsIn($activity->payload ?? [])),
            'audioMap' => $activity === null ? (object) [] : (object) app(BlockShaper::class)->audioMap(app(TestAudio::class)->scriptsOf($activity)),
            'aiDraft' => Test::isAiDraft($placement),
            'releaseUrl' => route('tests.questions.release', ['test' => $placement->placeable_id, 'placement' => $placement->id]),
            'details' => $this->questionDetails($item),
            'audio' => $this->questionAudio($placement),
            'updateUrl' => route('tests.questions.update', ['test' => $placement->placeable_id, 'placement' => $placement->id]),
            'deleteUrl' => route('tests.questions.destroy', ['test' => $placement->placeable_id, 'placement' => $placement->id]),
            'mediaUrl' => route('tests.questions.media', ['test' => $placement->placeable_id, 'placement' => $placement->id]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function preview(?Test $selected): array
    {
        $questions = $selected === null
            ? []
            : $selected->questions->values()->map(fn (ActivityPlacement $placement, int $index): array => $this->question($placement, $index + 1))->all();
        $question = $questions[0] ?? null;

        return [
            'position' => __(':current / :total', ['current' => $question === null ? 0 : 1, 'total' => $selected?->questions->count() ?? 0]),
            'imageCrop' => $this->thumbCrop(0),
            'question' => $question['text'] ?? __('Add a question to preview it.'),
            'options' => array_map(static fn (array $option): array => ['id' => $option['id'], 'text' => $option['text']], $question['options'] ?? []),
            'media' => $question['media'] ?? ['image' => null, 'audio' => null, 'video' => null],
            'questions' => $questions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function media(User $viewer, MediaService $media): array
    {
        $suggested = $media->library(
            $viewer,
            [MediaLibrary::GuesviaLibrary, MediaLibrary::Seed],
            null,
            '',
        )->limit(4)->get()->map(fn (MediaAsset $asset): array => [
            'id' => (string) $asset->id,
            'label' => $asset->label ?? $asset->original_name ?? 'Image',
            'url' => $asset->url(),
            'thumbUrl' => $asset->variantUrl('thumb'),
            'alt' => $asset->alt_text ?? '',
        ])->values()->all();

        return [
            'tabs' => [
                ['key' => 'image', 'label' => __('Image')],
                ['key' => 'audio', 'label' => __('Audio')],
                ['key' => 'video', 'label' => __('Video')],
            ],
            'activeTab' => 'image',
            'suggested' => $suggested,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function results(?Test $selected, User $viewer): array
    {
        // The builder's "Recent Results" summary card; the full result list
        // and the AI-judged answers live in Reports & Export.
        $query = TestAttempt::query()
            ->whereNotNull('submitted_at')
            ->when($selected !== null, fn (Builder $builder) => $builder->where('test_id', $selected->id))
            ->when($selected === null, function (Builder $builder) use ($viewer): void {
                $builder->whereHas('test', fn (Builder $tests): Builder => $this->scopeToReach($tests, $viewer));
            })
            ->latest('submitted_at');
        $attempts = $query->limit(25)->get();
        $completed = $attempts->count();
        $average = $attempts->avg(function (TestAttempt $attempt): float {
            return (float) ($attempt->max_score ?: 0) > 0 ? ((float) $attempt->score / (float) $attempt->max_score) * 100 : 0;
        }) ?? 0;

        return [
            'stats' => [
                ['key' => 'completed', 'value' => $completed, 'label' => __('Employees Completed'), 'detail' => $selected?->type->label() ?? __('Test'), 'tone' => 'brand'],
                ['key' => 'average', 'value' => (int) round((float) $average), 'unit' => '%', 'label' => __('Average Score'), 'detail' => $selected?->type->label() ?? __('Test'), 'tone' => 'success'],
            ],
        ];
    }

    /**
     * The read-only lines an admin needs to review a question the option
     * editor cannot show: situation, listening script, ordered lines, the
     * model answer (admin only, never on a test page — TEST-03).
     *
     * @param  array<string, mixed>  $item
     * @return list<array{label: string, text: string}>
     */
    private function questionDetails(array $item): array
    {
        $lines = [];

        foreach ([
            'situation' => __('Situation'),
            'scenario' => __('Situation'),
            'guest_audio_text' => __('Listening script'),
            'audio_text' => __('Listening script'),
            'instruction' => __('Instruction'),
            'model_answer' => __('Model answer'),
        ] as $key => $label) {
            if (is_string($item[$key] ?? null) && trim($item[$key]) !== '') {
                $lines[] = ['label' => $label, 'text' => $item[$key]];
            }
        }

        $sentences = is_array($item['sentences'] ?? null) ? $item['sentences'] : [];
        $order = is_array($item['order'] ?? null) ? $item['order'] : [];

        if ($sentences !== []) {
            $byId = [];

            foreach ($sentences as $sentence) {
                if (is_array($sentence) && is_string($sentence['text'] ?? null)) {
                    $byId[(string) ($sentence['id'] ?? '')] = $sentence['text'];
                }
            }

            foreach (($order !== [] ? $order : array_keys($byId)) as $position => $id) {
                if (isset($byId[(string) $id])) {
                    $lines[] = ['label' => __('Line :n', ['n' => $position + 1]), 'text' => $byId[(string) $id]];
                }
            }
        }

        return $lines;
    }

    /**
     * Stored audio state for a question with a listening script (PERF-04).
     *
     * @return array{status: string, failedReason: string|null, generateUrl: string}|null
     */
    private function questionAudio(ActivityPlacement $placement): ?array
    {
        $activity = $placement->activity;

        if ($activity === null) {
            return null;
        }

        $state = app(TestAudio::class)->status($activity);

        if ($state['status'] === 'none') {
            return null;
        }

        return [
            'status' => $state['status'],
            'failedReason' => $state['failedReason'],
            'generateUrl' => route('tests.questions.audio', ['test' => $placement->placeable_id, 'placement' => $placement->id]),
        ];
    }

    /**
     * The client's ten question types, as the builder lists them.
     *
     * @return list<array{value: string, label: string}>
     */
    private function kinds(): array
    {
        return array_map(
            static fn (ActivityType $type): array => ['value' => $type->value, 'label' => $type->builderLabel()],
            ActivityType::builderTypes(),
        );
    }

    private function assertOnTest(Test $test, ActivityPlacement $placement): void
    {
        abort_unless($placement->placeable_type === $test->getMorphClass() && (int) $placement->placeable_id === (int) $test->id, 404);
    }

    /**
     * The AI panel of the builder: generation state, the last request (for
     * Regenerate) and the Pre-test a Post-test can mirror (GEN-04, TSTM-03).
     *
     * @return array<string, mixed>|null
     */
    private function aiPanel(?Test $selected): ?array
    {
        if ($selected === null) {
            return null;
        }

        $request = is_array($selected->ai_request) ? $selected->ai_request : [];
        $source = app(TestQuestionGenerator::class)->pairedSource($selected);
        $skills = is_array($request['skills'] ?? null) ? $request['skills'] : [];

        return [
            'status' => $selected->ai_status?->value,
            'failedReason' => $selected->ai_failed_reason,
            'generateUrl' => route('tests.ai.generate', $selected),
            'regenerateUrl' => route('tests.ai.regenerate', $selected),
            'canRegenerate' => $skills !== [],
            'lastRequest' => [
                'count' => count($skills),
                'level' => is_string($request['level'] ?? null) ? $request['level'] : 'A2',
                'prompt' => is_string($request['prompt'] ?? null) ? $request['prompt'] : '',
                'paired' => ($request['paired_from'] ?? null) !== null,
            ],
            'pairedSource' => $source === null ? null : [
                'id' => $source->id,
                'title' => $source->title,
                'questionCount' => $source->questions()->count(),
            ],
            'skills' => array_map(static fn (TestQuestionSkill $skill): array => ['value' => $skill->value, 'label' => $skill->label()], TestQuestionSkill::cases()),
            'levels' => [
                ['value' => 'A1', 'label' => 'A1 · Beginner'],
                ['value' => 'A2', 'label' => 'A2 · Elementary'],
                ['value' => 'B1', 'label' => 'B1 · Intermediate'],
                ['value' => 'B2', 'label' => 'B2 · Upper intermediate'],
            ],
            'generateAudioUrl' => route('tests.audio.generate', $selected),
        ];
    }

    private function timeLimitMinutes(Test $test): ?int
    {
        $seconds = $test->settings['time_limit_seconds'] ?? null;

        return is_numeric($seconds) && (int) $seconds > 0 ? (int) round(((int) $seconds) / 60) : null;
    }

    /**
     * Keep the directory, cards and selected editor on one tenant boundary
     * (ROLE-02, SEC-01).
     *
     * @param  Builder<Test>  $query
     * @return Builder<Test>
     */
    private function scopeToReach(Builder $query, User $viewer): Builder
    {
        if ($viewer->hotel_id === null) {
            if (! $viewer->hasRole('super_admin')) {
                $query->whereNull('tests.hotel_id');
            }

            return $query;
        }

        return $query->where(function (Builder $scope) use ($viewer): void {
            $scope->whereNull('tests.hotel_id')->orWhere('tests.hotel_id', $viewer->hotel_id);
        });
    }

    /**
     * @return array{x: int, y: int, width: int, height: int}
     */
    private function thumbCrop(int $index): array
    {
        return [
            'x' => 208,
            'y' => 282 + (int) round(($index % 7) * 66.6),
            'width' => 46,
            'height' => 47,
        ];
    }
}

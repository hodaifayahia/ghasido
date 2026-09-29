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
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\Attempt;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\MediaAsset;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Content\MediaService;
use App\Services\Tests\QuestionImport;
use App\Services\Tests\TestAudio;
use App\Services\Tests\TestQuestionGenerator;
use App\Services\Tests\TestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
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

        return Inertia::render('admin/Tests', [
            'tabs' => [
                ['key' => 'tests', 'label' => __('Tests')],
                ['key' => 'question-bank', 'label' => __('Question Bank')],
                ['key' => 'results', 'label' => __('Results & Analytics')],
                ['key' => 'settings', 'label' => __('Settings')],
            ],
            'activeTab' => $this->activeTab($request),
            'builderOpen' => $selected !== null,
            'stats' => $this->stats($viewer),
            'list' => $this->listPayload($request, $tests, $viewer),
            'editor' => $this->editor($selected, $request, $viewer),
            'preview' => $this->preview($selected),
            'media' => $this->media($viewer, $media),
            'questionBank' => $this->questionBank(
                $viewer,
                array_map(
                    static fn (mixed $id): int => (int) $id,
                    $this->scopeToReach(Test::query(), $viewer)->pluck('id')->all(),
                ),
            ),
            'settings' => $this->settings($selected),
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

    public function storeQuestion(StoreTestQuestionRequest $request, Test $test, TestService $tests): RedirectResponse
    {
        Gate::authorize('update', $test);

        /** @var User $actor */
        $actor = $request->user();
        $tests->addQuestion($test, $request->questionData(), $actor);

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

        $tests->updateQuestion($placement, $request->questionData());

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
            ->withCount('questions')
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
            'description' => $description,
            'descriptionCount' => strlen($description).'/300',
            'kinds' => [
                ['value' => 'multiple_choice', 'label' => __('Multiple Choice')],
                ['value' => 'true_false', 'label' => __('True / False')],
                ['value' => 'fill_blank', 'label' => __('Fill in the Blank')],
                ['value' => 'matching', 'label' => __('Matching')],
                ['value' => 'short_answer', 'label' => __('Short Answer')],
                ['value' => 'audio', 'label' => __('Audio Question')],
                ['value' => 'image', 'label' => __('Image Question')],
                ['value' => 'video', 'label' => __('Video Question')],
                ['value' => 'speaking', 'label' => __('Speaking')],
                ['value' => 'ordering', 'label' => __('Ordering')],
                ['value' => 'writing', 'label' => __('Writing Activity')],
            ],
            'activeKind' => 'multiple_choice',
            // The per-question type menu also names the two kinds only an AI
            // draft creates, so such a question shows its real type.
            'questionKinds' => [
                ['value' => 'multiple_choice', 'label' => __('Multiple Choice')],
                ['value' => 'true_false', 'label' => __('True / False')],
                ['value' => 'fill_blank', 'label' => __('Fill in the Blank')],
                ['value' => 'matching', 'label' => __('Matching')],
                ['value' => 'short_answer', 'label' => __('Short Answer')],
                ['value' => 'audio', 'label' => __('Audio Question')],
                ['value' => 'image', 'label' => __('Image Question')],
                ['value' => 'video', 'label' => __('Video Question')],
                ['value' => 'speaking', 'label' => __('Speaking')],
                ['value' => 'ordering', 'label' => __('Ordering')],
                ['value' => 'writing', 'label' => __('Writing Activity')],
            ],
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
        $kind = $activity === null ? 'multiple_choice' : TestService::kindFor($activity->type, $activity->skill_label);
        $options = is_array($item['options'] ?? null) ? $item['options'] : [];
        $correct = (string) ($item['correct'] ?? '');
        $storedMedia = is_array($item['media'] ?? null) ? $item['media'] : [];
        $media = [];

        foreach (['image', 'audio', 'video'] as $mediaKind) {
            $mediaId = $storedMedia[$mediaKind] ?? $item[$mediaKind] ?? null;

            if (is_int($mediaId) || (is_string($mediaId) && ctype_digit($mediaId))) {
                $media[$mediaKind] = $this->mediaReference((int) $mediaId, $mediaKind);
            } else {
                $media[$mediaKind] = null;
            }
        }

        $targets = collect(is_array($item['targets'] ?? null) ? $item['targets'] : [])->keyBy(static fn (array $target): string => (string) ($target['id'] ?? ''));
        $prompts = collect(is_array($item['prompts'] ?? null) ? $item['prompts'] : [])->keyBy(static fn (array $prompt): string => (string) ($prompt['id'] ?? ''));
        $pairs = is_array($item['pairs'] ?? null) ? $item['pairs'] : [];
        $matchingPairs = [];

        foreach ($pairs as $promptId => $targetId) {
            $matchingPairs[] = [
                'left' => (string) ($prompts->get((string) $promptId)['audio_text'] ?? ''),
                'right' => (string) ($targets->get((string) $targetId)['label'] ?? ''),
            ];
        }

        $kindOptions = $options;
        if ($kind === 'ordering' && is_array($item['sentences'] ?? null)) {
            $sentences = collect($item['sentences'])->keyBy(static fn (array $sentence): string => (string) ($sentence['id'] ?? ''));
            $kindOptions = collect($item['order'] ?? array_keys($sentences->all()))
                ->map(static fn (string $id, int $index): array => [
                    'id' => chr(65 + $index),
                    'text' => (string) ($sentences->get($id)['text'] ?? ''),
                    'correct' => false,
                ])->values()->all();
        }

        return [
            'id' => $placement->id,
            'index' => $index,
            'kind' => $kind,
            'text' => (string) ($item['question'] ?? $activity->prompt),
            'imageCrop' => $this->thumbCrop($index),
            'options' => array_values(array_map(fn (array $option): array => [
                'id' => (string) ($option['id'] ?? ''),
                'text' => (string) ($option['text'] ?? $option['label'] ?? ''),
                'correct' => (string) ($option['id'] ?? '') === $correct,
                'imageId' => is_numeric($option['image'] ?? null) ? (int) $option['image'] : null,
                'image' => is_numeric($option['image'] ?? null) ? $this->mediaReference((int) $option['image'], 'image') : null,
                'audioId' => is_numeric($option['audio'] ?? null) ? (int) $option['audio'] : null,
                'audio' => is_numeric($option['audio'] ?? null) ? $this->mediaReference((int) $option['audio'], 'audio') : null,
                'audioText' => is_string($option['audio_text'] ?? null) ? $option['audio_text'] : null,
            ], $kindOptions)),
            'pairs' => $matchingPairs,
            'acceptedAnswers' => array_values(array_filter(is_array($item['accepted_answers'] ?? null) ? $item['accepted_answers'] : [], 'is_string')),
            'audioText' => (string) ($item['audio_text'] ?? ''),
            'requestText' => (string) ($item['request_text'] ?? ''),
            'information' => array_values(array_filter(is_array($item['information'] ?? null) ? $item['information'] : [], 'is_string')),
            'speakingSeconds' => (int) ($item['max_seconds'] ?? 30),
            'media' => $media,
            'typeLabel' => $activity?->type->label() ?? __('Question'),
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
    private function settings(?Test $selected): array
    {
        $settings = $selected === null ? [] : ($selected->settings ?? []);

        return [
            'toggles' => [
                ['key' => 'shuffle_questions', 'label' => __('Show questions in random order'), 'checked' => (bool) ($settings['shuffle_questions'] ?? false)],
                ['key' => 'shuffle_options', 'label' => __('Show options in random order'), 'checked' => (bool) ($settings['shuffle_options'] ?? false)],
                ['key' => 'single_attempt', 'label' => __('Allow only one attempt'), 'checked' => (bool) ($settings['single_attempt'] ?? false)],
                ['key' => 'show_results', 'label' => __('Show results immediately after completion'), 'checked' => ($settings['results_visibility'] ?? ResultsVisibility::Hidden->value) !== ResultsVisibility::Hidden->value],
                ['key' => 'show_answers', 'label' => __('Show correct answers'), 'checked' => (bool) ($settings['show_answers'] ?? false)],
                ['key' => 'motivational_message', 'label' => __('Add motivational message at the end'), 'checked' => (bool) ($settings['motivational_message'] ?? false)],
                ['key' => 'show_meaning', 'label' => __('Allow Show Meaning (Arabic) on questions'), 'checked' => (bool) ($settings['show_meaning'] ?? true)],
            ],
            'passMark' => (string) ($settings['pass_score'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function results(?Test $selected, User $viewer): array
    {
        $query = TestAttempt::query()
            ->with(['user:id,name,username', 'test:id,title,type'])
            ->whereNotNull('submitted_at')
            ->when($selected !== null, fn (Builder $builder) => $builder->where('test_id', $selected->id))
            ->when($selected === null, function (Builder $builder) use ($viewer): void {
                $builder->whereHas('test', fn (Builder $tests): Builder => $this->scopeToReach($tests, $viewer));
            })
            ->latest('submitted_at');
        $attempts = $query->limit(25)->get();
        $judged = $this->judgedAttempts($attempts->modelKeys());
        $completed = $attempts->count();
        $average = $attempts->avg(function (TestAttempt $attempt): float {
            return (float) ($attempt->max_score ?: 0) > 0 ? ((float) $attempt->score / (float) $attempt->max_score) * 100 : 0;
        }) ?? 0;

        return [
            'stats' => [
                ['key' => 'completed', 'value' => $completed, 'label' => __('Employees Completed'), 'detail' => $selected?->type->label() ?? __('Test'), 'tone' => 'brand'],
                ['key' => 'average', 'value' => (int) round((float) $average), 'unit' => '%', 'label' => __('Average Score'), 'detail' => $selected?->type->label() ?? __('Test'), 'tone' => 'success'],
            ],
            'rows' => $attempts->map(function (TestAttempt $attempt) use ($viewer, $judged): array {
                $summary = $attempt->scoreSummary();
                // Transcripts and recordings: Super Admin only (ROLE-04, PRIV-04).
                $answers = $this->judgedAnswers($judged->get($attempt->id, new BaseCollection), $viewer->hasRole('super_admin'));

                return [
                    'id' => $attempt->id,
                    'employee' => $attempt->user->name ?? $attempt->user->username ?? __('Unknown employee'),
                    'test' => $attempt->test->title ?? __('Test'),
                    'type' => $attempt->test->type->label(),
                    'score' => $summary['percent'] === null ? '—' : $summary['percent'].'%',
                    'submittedAt' => $attempt->submitted_at?->format('d M Y, H:i') ?? '—',
                    'answers' => $answers,
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  array<int, int>  $reachableTestIds
     * @return list<array<string, mixed>>
     */
    private function questionBank(User $viewer, array $reachableTestIds): array
    {
        $query = Activity::query()
            ->with(['department:id,name', 'hotel:id,name'])
            ->with(['placements' => function (Relation $placements) use ($reachableTestIds): void {
                $placements
                    ->where('placeable_type', (new Test)->getMorphClass())
                    ->whereIn('placeable_id', $reachableTestIds)
                    ->with('placeable');
            }])
            ->withCount('placements')
            ->whereHas('placements', fn (Builder $placements): Builder => $placements->where('placeable_type', (new Test)->getMorphClass()))
            ->orderByDesc('id');

        if ($viewer->hotel_id === null && ! $viewer->hasRole('super_admin')) {
            $query->whereNull('activities.hotel_id');
        } elseif ($viewer->hotel_id !== null) {
            $query->where(function (Builder $scope) use ($viewer): void {
                $scope->whereNull('activities.hotel_id')->orWhere('activities.hotel_id', $viewer->hotel_id);
            });
        }

        return array_values($query->limit(100)->get()->map(function (Activity $activity): array {
            $item = $activity->items()[0] ?? [];
            $department = $activity->department;
            $sourceTest = $activity->placements->first()?->placeable;

            return [
                'id' => $activity->id,
                'title' => (string) ($activity->title ?? __('Untitled question')),
                'kind' => $activity->type->value,
                'kindLabel' => $activity->type->label(),
                'prompt' => (string) ($item['question'] ?? $activity->prompt),
                'department' => (string) ($department === null ? __('All Departments') : $department->name),
                'uses' => (int) $activity->placements_count,
                'version' => $activity->current_version,
                'sourceTest' => $sourceTest instanceof Test
                    ? $sourceTest->title
                    : null,
                'openUrl' => $sourceTest instanceof Test
                    ? route('tests', ['test' => $sourceTest->id])
                    : null,
            ];
        })->all());
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

    /**
     * The AI-judged answers (speaking and writing) of the listed sittings,
     * grouped by sitting: one query for the whole results table instead of
     * one per row.
     *
     * @param  array<int, int>  $testAttemptIds
     * @return BaseCollection<int, BaseCollection<int, Attempt>>
     */
    private function judgedAttempts(array $testAttemptIds): BaseCollection
    {
        if ($testAttemptIds === []) {
            return new BaseCollection;
        }

        /** @var BaseCollection<int, BaseCollection<int, Attempt>> $grouped */
        $grouped = Attempt::query()
            ->with(['activity:id,type,prompt', 'activityVersion', 'responseMedia'])
            ->whereIn('test_attempt_id', $testAttemptIds)
            ->whereHas('activity', fn (Builder $activity): Builder => $activity->whereIn('type', [ActivityType::Speaking->value, ActivityType::Writing->value]))
            ->orderBy('id')
            ->get()
            ->toBase()
            ->groupBy('test_attempt_id');

        return $grouped;
    }

    /**
     * The AI-judged answers of one sitting (speaking and writing): the
     * structured verdict for everyone who may see results, the transcript
     * and recording for the Super Admin only (ROLE-04, PRIV-04, AIE-04).
     *
     * @param  BaseCollection<int, Attempt>  $rows
     * @return list<array<string, mixed>>
     */
    private function judgedAnswers(BaseCollection $rows, bool $fullAccess): array
    {
        return array_values($rows->map(function (Attempt $row) use ($fullAccess): array {
            $feedback = is_array($row->ai_feedback) ? $row->ai_feedback : [];
            $criteria = is_array($feedback['criteria'] ?? null) ? $feedback['criteria'] : [];
            $item = $row->activityVersion?->items()[0] ?? [];
            $raw = $row->raw_answer ?? [];
            $written = null;

            foreach ($raw as $value) {
                if (is_array($value) && is_string($value['text'] ?? null)) {
                    $written = $value['text'];
                }
            }

            $isSpeaking = $row->activity->type === ActivityType::Speaking;

            return [
                'id' => $row->id,
                'type' => $row->activity->type->value,
                'typeLabel' => $row->activity->type->label(),
                'question' => (string) ($item['question'] ?? $row->activity->prompt),
                'answerText' => $isSpeaking ? ($fullAccess ? $row->transcript : null) : ($fullAccess ? $written : null),
                'recordingUrl' => $isSpeaking && $fullAccess ? $row->responseMedia?->url() : null,
                'aiStatus' => $row->ai_status?->value,
                'failedReason' => $row->ai_failed_reason,
                'score' => $row->score === null ? null : (float) $row->score,
                'criteria' => array_map(
                    static fn (string $key, mixed $entry): array => [
                        'key' => $key,
                        'label' => ucfirst(str_replace('_', ' ', $key)),
                        'score' => is_array($entry) ? (int) ($entry['score'] ?? 0) : 0,
                        'comment' => is_array($entry) ? (string) ($entry['comment'] ?? '') : '',
                    ],
                    array_keys($criteria),
                    $criteria,
                ),
                'summary' => (string) ($feedback['summary'] ?? ''),
                'betterAnswer' => (string) ($feedback['better_answer'] ?? ''),
            ];
        })->all());
    }

    private function activeTab(Request $request): string
    {
        $tab = (string) $request->query('tab', 'tests');

        return in_array($tab, ['tests', 'question-bank', 'results', 'settings'], true) ? $tab : 'tests';
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

    /** @return array{id: string, label: string, url: string, thumbUrl: string, alt: string}|null */
    private function mediaReference(int $id, string $kind): ?array
    {
        $asset = MediaAsset::query()->find($id);

        if ($asset === null || $asset->kind->value !== $kind) {
            return null;
        }

        return [
            'id' => (string) $asset->id,
            'label' => $asset->label ?? $asset->original_name ?? ucfirst($kind),
            'url' => $asset->url(),
            'thumbUrl' => $asset->variantUrl('thumb'),
            'alt' => $asset->alt_text ?? '',
        ];
    }
}

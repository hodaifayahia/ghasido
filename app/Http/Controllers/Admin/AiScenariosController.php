<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Scenarios\StoreAiScenarioRequest;
use App\Http\Requests\Admin\Scenarios\UpdateAiScenarioRequest;
use App\Models\AiScenario;
use App\Models\Block;
use App\Models\Department;
use App\Models\RoleplayAttempt;
use App\Models\User;
use App\Services\Audio\AudioLibrary;
use App\Services\Scenarios\ScenarioConfiguration;
use App\Services\Scenarios\ScenarioService;
use App\Services\Tts\TtsSettings;
use App\Services\VoiceAgent\VoiceAgentSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Super Admin AI role-play scenarios screen (RP-01, RP-04, RP-05,
 * RP-08, RP-11, AIE-04, GEN-03, TSTM-05, ADM-02).
 *
 * The scenario library, editor, categories, instructions and feedback tabs
 * use persisted authoring configuration. "Preview & Test" is live: it runs a
 * real role-play against a stored scenario through Qwen and Deepgram, flagged
 * is_preview so it never counts toward an employee (RP-13).
 */
class AiScenariosController extends Controller
{
    public function store(StoreAiScenarioRequest $request, ScenarioService $scenarios): RedirectResponse
    {
        Gate::authorize('create', AiScenario::class);

        /** @var User $actor */
        $actor = $request->user();
        $scenario = $scenarios->create($request->scenarioData(), $actor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':title was created as a draft.', ['title' => $scenario->title]),
        ]);

        return to_route('ai-scenarios', ['scenario' => $scenario->id]);
    }

    public function update(UpdateAiScenarioRequest $request, AiScenario $scenario, ScenarioService $scenarios): RedirectResponse
    {
        Gate::authorize('update', $scenario);
        $scenarios->update($scenario, $request->scenarioData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scenario saved.')]);

        return back();
    }

    public function generate(Request $request, AiScenario $scenario, ScenarioService $scenarios): RedirectResponse
    {
        Gate::authorize('update', $scenario);
        $scenarios->generate($scenario);

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => __('Qwen is preparing an editable scenario draft.'),
        ]);

        return back();
    }

    public function applyDraft(Request $request, AiScenario $scenario, ScenarioService $scenarios): RedirectResponse
    {
        Gate::authorize('update', $scenario);
        $scenarios->applyDraft($scenario);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('AI draft applied. Review it before publishing.')]);

        return back();
    }

    public function publish(Request $request, AiScenario $scenario, ScenarioService $scenarios): RedirectResponse
    {
        Gate::authorize('update', $scenario);
        $scenarios->publish($scenario);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':title is published.', ['title' => $scenario->title])]);

        return back();
    }

    /**
     * "Delete" in the scenario table (CMS-01). Learner attempts are never
     * deleted: a scenario learners practised is removed from the library
     * and kept for reports (RP-11, DATA-10). Authorized here (SEC-01).
     */
    public function destroy(AiScenario $scenario, ScenarioService $scenarios): RedirectResponse
    {
        Gate::authorize('destroy', $scenario);

        $title = $scenario->title;
        $deleted = $scenarios->delete($scenario);

        Inertia::flash('toast', $deleted
            ? ['type' => 'success', 'message' => __(':title was deleted.', ['title' => $title])]
            : ['type' => 'success', 'message' => __(':title was removed from the library. Learner role-play attempts were kept for reports.', ['title' => $title])]);

        return to_route('ai-scenarios');
    }

    public function updateCategories(Request $request, ScenarioConfiguration $configuration): RedirectResponse
    {
        Gate::authorize('create', AiScenario::class);

        $data = $request->validate([
            'items' => ['required', 'array', 'max:50'],
            'items.*.id' => ['required', 'string', 'max:100'],
            'items.*.name' => ['required', 'string', 'max:100'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.department' => ['required', 'string', 'max:100'],
            'items.*.scenarioCount' => ['required', 'integer', 'min:0'],
            'items.*.status' => ['required', 'in:draft,published'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $configuration->put(ScenarioConfiguration::CATEGORIES, [
            'items' => array_values($data['items']),
        ], $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scenario categories saved.')]);

        return back();
    }

    public function updateInstructions(Request $request, ScenarioConfiguration $configuration): RedirectResponse
    {
        Gate::authorize('create', AiScenario::class);

        $data = $request->validate([
            'systemPrompt' => ['required', 'string', 'max:2000'],
            'tone' => ['required', 'string', 'max:50'],
            'strictness' => ['required', 'string', 'max:50'],
            'guardrails' => ['required', 'array', 'max:20'],
            'guardrails.*.id' => ['required', 'string', 'max:100'],
            'guardrails.*.text' => ['required', 'string', 'max:300'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $current = $configuration->get(ScenarioConfiguration::INSTRUCTIONS, []);
        $configuration->put(ScenarioConfiguration::INSTRUCTIONS, [
            ...$current,
            'systemPrompt' => trim($data['systemPrompt']),
            'tone' => $data['tone'],
            'strictness' => $data['strictness'],
            'guardrails' => array_values($data['guardrails']),
        ], $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('AI instructions saved.')]);

        return back();
    }

    public function updateFeedback(Request $request, ScenarioConfiguration $configuration): RedirectResponse
    {
        Gate::authorize('create', AiScenario::class);

        $data = $request->validate([
            'sections' => ['required', 'array', 'max:20'],
            'sections.*' => ['required', 'string', 'max:100'],
            'templates' => ['required', 'array', 'max:50'],
            'templates.*.id' => ['required', 'string', 'max:100'],
            'templates.*.name' => ['required', 'string', 'max:150'],
            'templates.*.description' => ['nullable', 'string', 'max:500'],
            'templates.*.tone' => ['required', 'string', 'max:100'],
            'templates.*.status' => ['required', 'in:draft,published'],
            'templates.*.isDefault' => ['required', 'boolean'],
            'templates.*.criteria' => ['required', 'array', 'max:20'],
            'templates.*.criteria.*.label' => ['required', 'string', 'max:100'],
            'templates.*.criteria.*.weight' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $configuration->put(ScenarioConfiguration::FEEDBACK, [
            'sections' => array_values($data['sections']),
            'templates' => array_values($data['templates']),
        ], $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Feedback templates saved.')]);

        return back();
    }

    public function __invoke(Request $request, TtsSettings $tts, AudioLibrary $audio, ScenarioConfiguration $configuration, VoiceAgentSettings $voiceAgent): Response
    {
        Gate::authorize('viewAny', AiScenario::class);

        $scenarioId = (string) $request->query('scenario', '');
        /** @var User $viewer */
        $viewer = $request->user();
        $selected = ctype_digit($scenarioId) && (int) $scenarioId > 0
            ? $this->visibleScenarios($viewer)->whereKey((int) $scenarioId)->first()
            : null;
        $departments = Department::query()
            ->visibleTo($viewer)
            ->active()
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name']);
        $databaseScenarios = $this->visibleScenarios($viewer)
            ->with('department')
            ->orderBy('title')
            ->get();
        $activeTab = $this->activeTab($request);
        $builderOpen = $activeTab === 'scenarios' && ($scenarioId === 'new' || in_array($scenarioId, [
            'guest-check-in',
            'room-service',
            'spa-information',
            'complaint',
            'directions',
            'lost-item',
            'special-requests',
        ], true) || $selected !== null);

        $payload = [
            'tabs' => [
                ['key' => 'scenarios', 'label' => __('Scenarios')],
                [
                    'key' => 'categories',
                    'label' => __('Scenario Categories'),
                ],
                ['key' => 'instructions', 'label' => __('AI Instructions')],
                ['key' => 'feedback', 'label' => __('Feedback Templates')],
                ['key' => 'preview', 'label' => __('Preview & Test')],
            ],
            'activeTab' => $activeTab,
            'builderOpen' => $builderOpen,
            'stats' => $this->stats($viewer),
            'library' => [
                'search' => '',
                'department' => 'all-departments',
                'status' => 'all-statuses',
                'departments' => [
                    [
                        'value' => 'all-departments',
                        'label' => __('All Departments'),
                    ],
                    ['value' => 'reception', 'label' => __('Reception')],
                    [
                        'value' => 'food-service',
                        'label' => __('Food & Beverage'),
                    ],
                    ['value' => 'spa', 'label' => __('Spa')],
                    [
                        'value' => 'housekeeping',
                        'label' => __('Housekeeping'),
                    ],
                ],
                'statuses' => [
                    ['value' => 'all-statuses', 'label' => __('All Statuses')],
                    ['value' => 'published', 'label' => __('Published')],
                    ['value' => 'draft', 'label' => __('Draft')],
                ],
                'scenarios' => [
                    [
                        'id' => 'guest-check-in',
                        'title' => __('Guest Check-in'),
                        'department' => __('Reception'),
                        'level' => __('Level: Beginner'),
                        'status' => 'published',
                        'crop' => [
                            'x' => 197,
                            'y' => 238,
                            'width' => 78,
                            'height' => 55,
                        ],
                    ],
                    [
                        'id' => 'room-service',
                        'title' => __('Room Service Request'),
                        'department' => __('Food & Beverage'),
                        'level' => __('Level: Elementary'),
                        'status' => 'published',
                        'crop' => [
                            'x' => 197,
                            'y' => 307,
                            'width' => 78,
                            'height' => 55,
                        ],
                    ],
                    [
                        'id' => 'spa-information',
                        'title' => __('Spa Information'),
                        'department' => __('Spa'),
                        'level' => __('Level: Elementary'),
                        'status' => 'draft',
                        'crop' => [
                            'x' => 197,
                            'y' => 376,
                            'width' => 78,
                            'height' => 55,
                        ],
                    ],
                    [
                        'id' => 'complaint',
                        'title' => __('Handling a Complaint'),
                        'department' => __('Reception'),
                        'level' => __('Level: Intermediate'),
                        'status' => 'published',
                        'crop' => [
                            'x' => 197,
                            'y' => 445,
                            'width' => 78,
                            'height' => 55,
                        ],
                    ],
                    [
                        'id' => 'directions',
                        'title' => __('Giving Directions'),
                        'department' => __('Reception'),
                        'level' => __('Level: Beginner'),
                        'status' => 'published',
                        'crop' => [
                            'x' => 197,
                            'y' => 514,
                            'width' => 78,
                            'height' => 55,
                        ],
                    ],
                    [
                        'id' => 'lost-item',
                        'title' => __('Lost Item'),
                        'department' => __('Housekeeping'),
                        'level' => __('Level: Intermediate'),
                        'status' => 'draft',
                        'crop' => [
                            'x' => 197,
                            'y' => 583,
                            'width' => 78,
                            'height' => 55,
                        ],
                    ],
                    [
                        'id' => 'special-requests',
                        'title' => __('Special Requests'),
                        'department' => __('Food & Beverage'),
                        'level' => __('Level: Intermediate'),
                        'status' => 'published',
                        'crop' => [
                            'x' => 197,
                            'y' => 652,
                            'width' => 78,
                            'height' => 55,
                        ],
                    ],
                ],
                'showing' => __('Showing 1-7 of 24 scenarios'),
                'pages' => [1, 2, 3, 4],
                'currentPage' => 1,
            ],
            'editor' => $this->editorPayload($scenarioId, [
                'status' => __('Published'),
                'title' => __('Guest Check-in'),
                'titleCount' => '15/100',
                'department' => 'reception',
                'level' => 'beginner',
                'departments' => [
                    ['value' => 'reception', 'label' => __('Reception')],
                    ['value' => 'spa', 'label' => __('Spa')],
                    [
                        'value' => 'food-service',
                        'label' => __('Food & Beverage'),
                    ],
                    [
                        'value' => 'housekeeping',
                        'label' => __('Housekeeping'),
                    ],
                ],
                'levels' => [
                    ['value' => 'beginner', 'label' => __('Beginner')],
                    ['value' => 'elementary', 'label' => __('Elementary')],
                    [
                        'value' => 'intermediate',
                        'label' => __('Intermediate'),
                    ],
                ],
                'coverCrop' => [
                    'x' => 544,
                    'y' => 313,
                    'width' => 245,
                    'height' => 94,
                ],
                'description' => 'The guest arrives at the hotel. They want to check in, ask about the room, and get some information about hotel facilities.',
                'descriptionCount' => '104/500',
                'guestRole' => 'You are an international guest. You are polite and friendly. You want to check in, ask some questions and make a special request.',
                'employeeRole' => 'You are a hotel receptionist. Welcome the guest, check their reservation, provide information, and respond to their requests professionally.',
                'objectives' => [
                    'Use polite greetings and welcoming language',
                    'Ask and answer questions about the reservation',
                    'Provide information about hotel facilities',
                    'Handle special requests politely',
                ],
                'usedInLessons' => [],
            ]),
            'preview' => [
                'messages' => [
                    [
                        'id' => 1,
                        'actor' => 'guest',
                        'text' => 'Good afternoon! I have a reservation under the name Smith.',
                    ],
                    [
                        'id' => 2,
                        'actor' => 'employee',
                        'text' => 'Good afternoon, Mr. Smith! Welcome to our hotel. Let me check your reservation, please.',
                        'avatarCrop' => [
                            'x' => 1173,
                            'y' => 274,
                            'width' => 39,
                            'height' => 46,
                        ],
                    ],
                    [
                        'id' => 3,
                        'actor' => 'guest',
                        'text' => 'Thank you. Can you tell me what time the breakfast is?',
                    ],
                    [
                        'id' => 4,
                        'actor' => 'employee',
                        'text' => 'Of course. Breakfast is served from 6:30 a.m. to 10:00 a.m. in our main restaurant.',
                        'avatarCrop' => [
                            'x' => 1173,
                            'y' => 387,
                            'width' => 39,
                            'height' => 47,
                        ],
                    ],
                ],
                'placeholder' => __('Type your message here...'),
            ],
            'settings' => [
                'attempts' => '3',
                'attemptOptions' => [
                    ['value' => '1', 'label' => '1'],
                    ['value' => '2', 'label' => '2'],
                    ['value' => '3', 'label' => '3'],
                ],
                'feedbackStyle' => 'encouraging',
                'feedbackStyles' => [
                    [
                        'value' => 'encouraging',
                        'label' => __('Encouraging and constructive'),
                    ],
                    [
                        'value' => 'balanced',
                        'label' => __('Balanced coaching'),
                    ],
                ],
                'focusAreas' => [
                    ['label' => __('Vocabulary'), 'checked' => true],
                    ['label' => __('Grammar'), 'checked' => true],
                    ['label' => __('Pronunciation'), 'checked' => true],
                    ['label' => __('Fluency'), 'checked' => true],
                    ['label' => __('Task Completion'), 'checked' => true],
                ],
                'allowHints' => true,
                'showSuggestions' => true,
                'tags' => [
                    'check-in',
                    'reservation',
                    'greetings',
                    'hotel facilities',
                ],
            ],
            'categories' => [
                'summary' => [
                    ['label' => __('Categories'), 'value' => '6', 'tone' => 'brand'],
                    ['label' => __('Published'), 'value' => '5', 'tone' => 'success'],
                    ['label' => __('Total Scenarios'), 'value' => '24', 'tone' => 'ai'],
                    ['label' => __('Drafts'), 'value' => '1', 'tone' => 'warning'],
                ],
                'items' => [
                    [
                        'id' => 'front-desk-essentials',
                        'name' => __('Front Desk Essentials'),
                        'description' => __('Check-in, check-out, reservations and everyday reception conversations.'),
                        'department' => __('Reception'),
                        'scenarioCount' => 8,
                        'status' => 'published',
                    ],
                    [
                        'id' => 'dining-room-service',
                        'name' => __('Dining & Room Service'),
                        'description' => __('Taking orders, room service requests and restaurant conversations.'),
                        'department' => __('Food & Beverage'),
                        'scenarioCount' => 6,
                        'status' => 'published',
                    ],
                    [
                        'id' => 'housekeeping-requests',
                        'name' => __('Housekeeping Requests'),
                        'description' => __('Extra towels, cleaning schedules and in-room maintenance requests.'),
                        'department' => __('Housekeeping'),
                        'scenarioCount' => 4,
                        'status' => 'published',
                    ],
                    [
                        'id' => 'spa-wellness',
                        'name' => __('Spa & Wellness'),
                        'description' => __('Bookings, treatment information and guiding guests around the spa.'),
                        'department' => __('Spa'),
                        'scenarioCount' => 3,
                        'status' => 'published',
                    ],
                    [
                        'id' => 'complaints-problem-solving',
                        'name' => __('Complaints & Problem Solving'),
                        'description' => __('Apologising, explaining and offering a solution when things go wrong.'),
                        'department' => __('All Departments'),
                        'scenarioCount' => 2,
                        'status' => 'published',
                    ],
                    [
                        'id' => 'concierge-local-info',
                        'name' => __('Concierge & Local Info'),
                        'description' => __('Directions, local recommendations and transport for guests.'),
                        'department' => __('Reception'),
                        'scenarioCount' => 1,
                        'status' => 'draft',
                    ],
                ],
            ],
            'instructions' => [
                'systemPrompt' => "You are role-playing a specific hotel situation to help a hotel employee practise English.\n\nStay completely in character as {{ai_role}}. Never say you are an AI or offer help outside the scenario. Keep every reply short, natural and easy to understand for a basic English level. Guide the conversation towards the objective: {{objective}}. End the conversation once the objective is met or after {{max_turns}} turns.",
                'systemPromptCount' => '486/2000',
                'tone' => 'encouraging',
                'toneOptions' => [
                    ['value' => 'encouraging', 'label' => __('Encouraging and friendly')],
                    ['value' => 'neutral', 'label' => __('Neutral and professional')],
                    ['value' => 'firm', 'label' => __('Firm but polite')],
                ],
                'strictness' => 'communicative',
                'strictnessOptions' => [
                    ['value' => 'communicative', 'label' => __('Reward communicative success')],
                    ['value' => 'balanced', 'label' => __('Balance accuracy and fluency')],
                    ['value' => 'strict', 'label' => __('Strict language accuracy')],
                ],
                'guardrails' => [
                    ['id' => 'in-character', 'text' => 'Never break character or act as a general assistant.'],
                    ['id' => 'scope', 'text' => "Stay inside the hotel situation and the employee's role."],
                    ['id' => 'level', 'text' => 'Use short, clear sentences suited to A1–A2 learners.'],
                    ['id' => 'success', 'text' => 'Reward getting the message across over perfect grammar.'],
                    ['id' => 'safety', 'text' => 'Never request personal data or leave the training context.'],
                ],
                'variables' => [
                    ['token' => '{{ai_role}}', 'description' => __('The character the AI plays')],
                    ['token' => '{{employee_role}}', 'description' => __("The employee's job in the scene")],
                    ['token' => '{{objective}}', 'description' => __('What the employee must achieve')],
                    ['token' => '{{expected_language}}', 'description' => __('Phrases the employee should use')],
                    ['token' => '{{max_turns}}', 'description' => __('Maximum conversation length')],
                ],
            ],
            'feedback' => [
                'sections' => [
                    __('What you did well'),
                    __('What to improve'),
                    __('Better expression'),
                    __('Key phrase to remember'),
                ],
                'templates' => [
                    [
                        'id' => 'standard',
                        'name' => __('Standard Role-play Feedback'),
                        'description' => __('Balanced criteria for everyday guest conversations.'),
                        'tone' => __('Encouraging'),
                        'status' => 'published',
                        'isDefault' => true,
                        'criteria' => [
                            ['label' => __('Vocabulary'), 'weight' => 20],
                            ['label' => __('Grammar'), 'weight' => 15],
                            ['label' => __('Fluency'), 'weight' => 20],
                            ['label' => __('Pronunciation'), 'weight' => 15],
                            ['label' => __('Politeness'), 'weight' => 15],
                            ['label' => __('Task Completion'), 'weight' => 15],
                        ],
                    ],
                    [
                        'id' => 'guest-service',
                        'name' => __('Reception — Guest Service'),
                        'description' => __('Weighted towards politeness and completing the guest request.'),
                        'tone' => __('Encouraging'),
                        'status' => 'published',
                        'isDefault' => false,
                        'criteria' => [
                            ['label' => __('Politeness'), 'weight' => 25],
                            ['label' => __('Task Completion'), 'weight' => 25],
                            ['label' => __('Vocabulary'), 'weight' => 20],
                            ['label' => __('Fluency'), 'weight' => 20],
                            ['label' => __('Grammar'), 'weight' => 10],
                        ],
                    ],
                    [
                        'id' => 'complaint-handling',
                        'name' => __('Complaint Handling'),
                        'description' => __('Focuses on staying calm, apologising and offering a solution.'),
                        'tone' => __('Balanced'),
                        'status' => 'draft',
                        'isDefault' => false,
                        'criteria' => [
                            ['label' => __('Task Completion'), 'weight' => 30],
                            ['label' => __('Politeness'), 'weight' => 25],
                            ['label' => __('Fluency'), 'weight' => 20],
                            ['label' => __('Vocabulary'), 'weight' => 15],
                            ['label' => __('Grammar'), 'weight' => 10],
                        ],
                    ],
                ],
            ],
            'previewTest' => $this->previewPayload($request, $audio),
            'tts' => $tts->payload(),
        ];

        $storedCategories = $configuration->get(
            ScenarioConfiguration::CATEGORIES,
            ['items' => $payload['categories']['items']],
        );
        $categoryItems = is_array($storedCategories['items'] ?? null)
            ? array_values($storedCategories['items'])
            : $payload['categories']['items'];
        $payload['categories'] = [
            'summary' => $this->categorySummary($categoryItems, $databaseScenarios->count()),
            'items' => $categoryItems,
            'saveUrl' => route('ai-scenarios.config.categories'),
        ];

        $storedInstructions = $configuration->get(
            ScenarioConfiguration::INSTRUCTIONS,
            $payload['instructions'],
        );
        $payload['instructions'] = [
            ...$payload['instructions'],
            ...$storedInstructions,
            'systemPromptCount' => mb_strlen((string) ($storedInstructions['systemPrompt'] ?? $payload['instructions']['systemPrompt'])).'/2000',
            'saveUrl' => route('ai-scenarios.config.instructions'),
        ];

        $storedFeedback = $configuration->get(
            ScenarioConfiguration::FEEDBACK,
            $payload['feedback'],
        );
        $payload['feedback'] = [
            ...$payload['feedback'],
            ...$storedFeedback,
            'saveUrl' => route('ai-scenarios.config.feedback'),
        ];

        $payload['library']['departments'] = [
            ['value' => 'all-departments', 'label' => __('All Departments')],
            ...$departments->map(fn (Department $department): array => [
                'value' => (string) $department->id,
                'label' => $department->name,
            ])->all(),
        ];

        if ($databaseScenarios->isNotEmpty()) {
            // The delete dialog says up front whether learners practised the
            // scenario and which lesson steps offer it (DATA-10, RP-14).
            $learnerAttempts = app(ScenarioService::class)->learnerAttemptCounts(
                array_values($databaseScenarios->modelKeys()),
            );
            $lessonSteps = Block::query()
                ->join('block_ai_scenario', 'block_ai_scenario.block_id', '=', 'blocks.id')
                ->whereIn('block_ai_scenario.ai_scenario_id', $databaseScenarios->modelKeys())
                ->selectRaw('block_ai_scenario.ai_scenario_id as scenario_key, count(*) as total')
                ->groupBy('block_ai_scenario.ai_scenario_id')
                ->pluck('total', 'scenario_key')
                ->all();
            $payload['library']['scenarios'] = $databaseScenarios->map(fn (AiScenario $scenario): array => [
                'id' => (string) $scenario->id,
                'title' => $scenario->title,
                'department' => $scenario->department->name ?? __('Unknown Department'),
                'level' => __('Level: :level', ['level' => Str::headline($scenario->difficulty->value)]),
                'status' => $scenario->status->value,
                'canDelete' => $viewer->can('destroy', $scenario),
                'learnerAttempts' => $learnerAttempts[$scenario->id] ?? 0,
                'lessonSteps' => (int) ($lessonSteps[$scenario->id] ?? 0),
                'crop' => [
                    'x' => 197,
                    'y' => 238 + (($scenario->id % 7) * 69),
                    'width' => 78,
                    'height' => 55,
                ],
            ])->values()->all();
            $payload['library']['showing'] = __('Showing 1-:count of :count scenarios', [
                'count' => $databaseScenarios->count(),
            ]);
            $payload['library']['pages'] = [1];
            $payload['library']['currentPage'] = 1;
        }

        if ($selected !== null) {
            $payload['editor'] = $this->databaseEditor($selected, $payload['editor'], $departments);
            $payload['settings'] = $this->scenarioSettings($selected);
        }

        // Live voice call controls and the editor's "Test voice call"
        // (spec 0004, RP-13). No key crosses this boundary (SEC-03).
        $payload['voiceAgent'] = [
            'settings' => $voiceAgent->payload(),
            'scenario' => $selected !== null ? [
                'id' => $selected->id,
                'title' => $selected->title,
                'guestRole' => $selected->ai_role,
                'overrides' => $voiceAgent->scenarioOverrides($selected),
                'saveUrl' => route('ai-scenarios.voice-agent.scenario', $selected),
                'startUrl' => route('ai-scenarios.voice-preview.start', $selected),
            ] : null,
        ];

        return Inertia::render('admin/AiScenarios', $payload);
    }

    /**
     * Directory cards are intentionally derived from the scenario library
     * used by the approved authoring screen (RP-01, RP-04, GEN-03).
     *
     * @return list<array{key: string, value: int, label: string, detail?: string}>
     */
    private function stats(User $viewer): array
    {
        $scenarios = $this->visibleScenarios($viewer)->get(['id', 'status', 'department_id']);
        $published = $scenarios->where('status', 'published')->count();

        return [
            [
                'key' => 'totalScenarios',
                'value' => $scenarios->count(),
                'label' => __('Total Scenarios'),
                'detail' => __('Across all departments'),
            ],
            [
                'key' => 'publishedScenarios',
                'value' => $published,
                'label' => __('Published'),
                'detail' => __('Ready for employees'),
            ],
            [
                'key' => 'draftScenarios',
                'value' => $scenarios->count() - $published,
                'label' => __('Draft Scenarios'),
                'detail' => __('Still in progress'),
            ],
            [
                'key' => 'departments',
                'value' => $scenarios->pluck('department_id')->unique()->count(),
                'label' => __('Departments'),
                'detail' => __('With active scenarios'),
            ],
        ];
    }

    /**
     * Shape a database scenario for the editor while preserving the approved
     * shell copy for fields that are not yet stored in the mockup (RP-01,
     * RP-04, GEN-03).
     *
     * @param  Collection<int, Department>  $departments
     * @param  array<string, mixed>  $editor
     * @return array<string, mixed>
     */
    private function databaseEditor(AiScenario $scenario, array $editor, Collection $departments): array
    {
        $goals = array_values(array_filter($scenario->goals ?? [], static fn (string $goal): bool => trim($goal) !== ''));

        return array_replace($editor, [
            'id' => $scenario->id,
            'status' => $scenario->status->label(),
            'title' => $scenario->title,
            'titleCount' => mb_strlen($scenario->title).'/100',
            'department' => (string) $scenario->department_id,
            'level' => $scenario->difficulty->value,
            'description' => $scenario->description,
            'descriptionCount' => mb_strlen($scenario->description).'/500',
            'guestRole' => $scenario->ai_role,
            'employeeRole' => $scenario->employee_role,
            'objectives' => $goals,
            'situation' => $scenario->situation,
            'objective' => $scenario->objective,
            'usefulPhrases' => $scenario->useful_phrases ?? [],
            'aiStatus' => $scenario->ai_status?->value,
            'aiDraft' => $scenario->ai_draft,
            'saveUrl' => route('ai-scenarios.update', $scenario),
            'generateUrl' => route('ai-scenarios.generate', $scenario),
            'applyDraftUrl' => route('ai-scenarios.apply-draft', $scenario),
            'publishUrl' => route('ai-scenarios.publish', $scenario),
            'usedInLessons' => $this->scenarioLessonUsage($scenario),
            'departments' => $departments->map(fn (Department $department): array => [
                'value' => (string) $department->id,
                'label' => $department->name,
            ])->values()->all(),
        ]);
    }

    /**
     * Show the lesson steps that currently offer this reusable scenario. The
     * association is edited from the lesson's AI Role-play block so one
     * scenario can be reused by multiple lessons (RP-14, TSTM-05).
     *
     * @return list<array<string, mixed>>
     */
    private function scenarioLessonUsage(AiScenario $scenario): array
    {
        return array_values($scenario->blocks()
            ->with(['lesson.course', 'lesson.unit'])
            ->get()
            ->map(function (Block $block): ?array {
                $lesson = $block->lesson;

                if ($lesson === null || $lesson->isArchived()) {
                    return null;
                }

                return [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'course' => $lesson->course?->title,
                    'unit' => $lesson->unit?->title,
                    'blockId' => $block->id,
                    'status' => $lesson->status->value,
                    'url' => route('lessons.edit', $lesson),
                ];
            })
            ->filter()
            ->unique('id')
            ->values()
            ->all());
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{label: string, value: string, tone: string, detail: string}>
     */
    private function categorySummary(array $items, int $scenarioCount): array
    {
        $published = count(array_filter($items, static fn (array $item): bool => ($item['status'] ?? 'draft') === 'published'));

        return [
            ['label' => __('Categories'), 'value' => (string) count($items), 'tone' => 'brand', 'detail' => __('Reusable scenario groups')],
            ['label' => __('Published'), 'value' => (string) $published, 'tone' => 'success', 'detail' => __('Available in the library')],
            ['label' => __('Total Scenarios'), 'value' => (string) $scenarioCount, 'tone' => 'ai', 'detail' => __('Across all categories')],
            ['label' => __('Drafts'), 'value' => (string) (count($items) - $published), 'tone' => 'warning', 'detail' => __('Still in progress')],
        ];
    }

    /** @return array<string, mixed> */
    private function scenarioSettings(AiScenario $scenario): array
    {
        $settings = $scenario->settings ?? [];

        return [
            'attempts' => (string) ($scenario->attempts_allowed ?: 3),
            'attemptOptions' => [
                ['value' => '1', 'label' => '1'],
                ['value' => '2', 'label' => '2'],
                ['value' => '3', 'label' => '3'],
                ['value' => '4', 'label' => '4'],
                ['value' => '5', 'label' => '5'],
            ],
            'feedbackStyle' => (string) ($settings['feedback_style'] ?? 'encouraging'),
            'feedbackStyles' => [
                ['value' => 'encouraging', 'label' => __('Encouraging and constructive')],
                ['value' => 'balanced', 'label' => __('Balanced coaching')],
                ['value' => 'direct', 'label' => __('Direct and concise')],
            ],
            'focusAreas' => array_values(array_map(
                static fn (array $area): array => [
                    'label' => (string) ($area['label'] ?? ''),
                    'checked' => (bool) ($area['checked'] ?? false),
                ],
                is_array($settings['focus_areas'] ?? null)
                    ? $settings['focus_areas']
                    : [
                        ['label' => __('Vocabulary'), 'checked' => true],
                        ['label' => __('Grammar'), 'checked' => true],
                        ['label' => __('Pronunciation'), 'checked' => true],
                        ['label' => __('Fluency'), 'checked' => true],
                        ['label' => __('Task Completion'), 'checked' => true],
                    ],
            )),
            'allowHints' => (bool) ($settings['allow_hints'] ?? true),
            'showSuggestions' => (bool) ($settings['show_suggestions'] ?? true),
            'tags' => array_values(array_filter($settings['tags'] ?? [], 'is_string')),
            'saveUrl' => route('ai-scenarios.update', $scenario),
        ];
    }

    /**
     * The builder remains one shared editor, but the opened library row fills
     * its identifying fields so the selected scenario is visible immediately
     * (RP-01, RP-04).
     *
     * @param  array<string, mixed>  $editor
     * @return array<string, mixed>
     */
    private function editorPayload(string $scenarioId, array $editor): array
    {
        $presets = [
            'guest-check-in' => [
                'title' => __('Guest Check-in'),
                'department' => 'reception',
                'level' => 'beginner',
                'status' => __('Published'),
            ],
            'room-service' => [
                'title' => __('Room Service Request'),
                'department' => 'food-service',
                'level' => 'elementary',
                'status' => __('Published'),
            ],
            'spa-information' => [
                'title' => __('Spa Information'),
                'department' => 'spa',
                'level' => 'elementary',
                'status' => __('Draft'),
            ],
            'complaint' => [
                'title' => __('Handling a Complaint'),
                'department' => 'reception',
                'level' => 'intermediate',
                'status' => __('Published'),
            ],
            'directions' => [
                'title' => __('Giving Directions'),
                'department' => 'reception',
                'level' => 'beginner',
                'status' => __('Published'),
            ],
            'lost-item' => [
                'title' => __('Lost Item'),
                'department' => 'housekeeping',
                'level' => 'intermediate',
                'status' => __('Draft'),
            ],
            'special-requests' => [
                'title' => __('Special Requests'),
                'department' => 'food-service',
                'level' => 'intermediate',
                'status' => __('Published'),
            ],
        ];

        $preset = $presets[$scenarioId] ?? [
            'title' => (string) request()->query('newTitle', __('New Scenario')),
            'department' => (string) request()->query('newDepartment', 'reception'),
            'level' => (string) request()->query('newLevel', 'beginner'),
            'status' => __('Draft'),
        ];

        return array_replace($editor, [
            ...$preset,
            'titleCount' => mb_strlen((string) $preset['title']).'/100',
        ]);
    }

    private function activeTab(Request $request): string
    {
        $tab = (string) $request->query('tab', '');

        if (in_array($tab, ['scenarios', 'categories', 'instructions', 'feedback', 'preview'], true)) {
            return $tab;
        }

        return $request->integer('preview') > 0 ? 'preview' : 'scenarios';
    }

    /** @return Builder<AiScenario> */
    private function visibleScenarios(User $viewer): Builder
    {
        return AiScenario::query()->notArchived()->when(
            ! $viewer->hasRole('super_admin'),
            fn (Builder $query) => $query->where(function (Builder $scope) use ($viewer): void {
                $scope->whereNull('hotel_id');

                if ($viewer->hotel_id !== null) {
                    $scope->orWhere('hotel_id', $viewer->hotel_id);
                }
            }),
        );
    }

    /**
     * The live "Preview & Test" payload: the real scenarios to test, and the
     * active preview conversation if one is open (RP-13).
     *
     * @return array<string, mixed>
     */
    private function previewPayload(Request $request, AudioLibrary $audio): array
    {
        $models = $this->visibleScenarios($request->user('web'))
            ->with('department')
            ->orderBy('title')
            ->get();

        $scenarios = $models->map(fn (AiScenario $scenario): array => [
            'value' => (string) $scenario->id,
            'label' => $scenario->title,
            'department' => $scenario->department->name ?? __('Unknown Department'),
            'level' => Str::headline($scenario->difficulty->value),
            'situation' => $scenario->situation,
            'aiRole' => $scenario->ai_role,
            'employeeRole' => $scenario->employee_role,
            'objective' => $scenario->objective,
            'quote' => $scenario->quote,
            'icon' => $scenario->icon,
            'status' => $scenario->status->value,
        ])->all();

        $attempt = $this->activePreview($request);
        // "Test Scenario" from the editor names the scenario to test; any
        // other visit starts on the first one (client report 2026-09-30).
        $asked = $request->integer('scenario');
        $firstId = $models->contains('id', $asked) ? $asked : $models->first()?->id;

        return [
            'scenarios' => $scenarios,
            'selected' => $attempt !== null
                ? (string) $attempt->ai_scenario_id
                : ($firstId !== null ? (string) $firstId : ''),
            'startUrl' => route('ai-scenarios.preview.store'),
            'placeholder' => __('Type a reply to test the scenario...'),
            'notSavedNote' => __('Preview conversations are not saved to any employee record (RP-13).'),
            'attempt' => $attempt !== null ? $this->attemptPayload($attempt, $audio) : null,
        ];
    }

    private function activePreview(Request $request): ?RoleplayAttempt
    {
        $id = $request->integer('preview');

        if ($id <= 0) {
            return null;
        }

        /** @var User $user */
        $user = $request->user();

        return RoleplayAttempt::query()
            ->where('is_preview', true)
            ->where('user_id', $user->id)
            ->with('scenario')
            ->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function attemptPayload(RoleplayAttempt $attempt, AudioLibrary $audio): array
    {
        $scenario = $attempt->scenario;

        $guestTexts = [];
        foreach ($attempt->transcript as $turn) {
            if ($turn['role'] === RoleplayAttempt::ROLE_GUEST) {
                $guestTexts[] = (string) $turn['text'];
            }
        }

        $audioUrls = $audio->urlsFor($guestTexts);

        $turns = [];
        foreach ($attempt->transcript as $index => $turn) {
            $text = (string) $turn['text'];
            $turns[] = [
                'id' => $index,
                'role' => $turn['role'],
                'text' => $text,
                'audio' => $turn['role'] === RoleplayAttempt::ROLE_GUEST
                    ? ($audioUrls[$text]['normal'] ?? null)
                    : null,
                'at' => $turn['at'],
            ];
        }

        $minTurns = $scenario->min_turns;

        return [
            'id' => $attempt->id,
            'scenarioId' => (string) $attempt->ai_scenario_id,
            'scenarioTitle' => $scenario->title,
            'status' => $attempt->status->value,
            'aiStatus' => $attempt->ai_status?->value,
            'pendingReply' => $attempt->pending_reply,
            'failedReason' => $attempt->failed_reason,
            'transcript' => $turns,
            'employeeTurns' => $attempt->employeeTurnsCount(),
            'minTurns' => $minTurns,
            'canEnd' => $attempt->status->acceptsTurns() && $attempt->employeeTurnsCount() >= $minTurns,
            'overallScore' => $attempt->overall_score,
            'criteriaScores' => $attempt->criteria_scores ?? [],
            'feedback' => $attempt->feedback,
            'messageUrl' => route('ai-scenarios.preview.message', ['attempt' => $attempt->id]),
            'endUrl' => route('ai-scenarios.preview.end', ['attempt' => $attempt->id]),
            'resetUrl' => route('ai-scenarios.preview.destroy', ['attempt' => $attempt->id]),
        ];
    }
}

<?php

namespace App\Http\Controllers\Learn;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Learn\Concerns\AuthorizesRoleplayStep;
use App\Http\Requests\Learn\RoleplayMessageRequest;
use App\Models\AiScenario;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Models\RoleplayAttempt;
use App\Models\User;
use App\Services\Ai\AiLimitReached;
use App\Services\Learning\PayloadResolver;
use App\Services\Learning\RoleplayService;
use App\Services\VoiceAgent\VoiceAgentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * AI role-play (RP-01..11, AIE-04, AIL-03, PERF-04; spec 0003 Part E,
 * photos 15–18).
 *
 * The guest's turns are produced by a queued job and the chat page polls
 * `pending_reply`; the daily limit is checked before a turn, never before
 * the evaluation, so a finished conversation can always be scored (AIL-03).
 */
class RoleplayController extends Controller
{
    use AuthorizesRoleplayStep;

    public function __construct(
        private readonly RoleplayService $roleplay,
        private readonly PayloadResolver $resolver,
    ) {}

    public function ready(Request $request, Lesson $lesson, Block $block, AiScenario $scenario): Response
    {
        $user = $this->learner($request);
        $this->assertReach($user, $scenario);
        $this->authorizeRoleplayStep($lesson, $block, $scenario);

        return Inertia::render('employee/roleplay/Ready', [
            'scenario' => $this->brief($scenario, $user),
            'startUrl' => route('learn.roleplay.start', ['lesson' => $lesson, 'block' => $block, 'scenario' => $scenario]),
            'backUrl' => route('learn.lessons.step', ['lesson' => $lesson, 'block' => $block]),
            'canStart' => $this->roleplay->canStart($user, $scenario),
            // Live spoken call (spec 0004): offered alongside the text chat
            // when the scenario accepts voice and the server has a key.
            'voiceCall' => $scenario->acceptsVoice() && app(VoiceAgentSettings::class)->apiConfigured()
                ? [
                    'startUrl' => route('learn.roleplay.voice.start', ['lesson' => $lesson, 'block' => $block, 'scenario' => $scenario]),
                    'guestRole' => $scenario->ai_role,
                ]
                : null,
        ]);
    }

    public function start(Request $request, Lesson $lesson, Block $block, AiScenario $scenario): RedirectResponse
    {
        $user = $this->learner($request);
        $this->assertReach($user, $scenario);
        $this->authorizeRoleplayStep($lesson, $block, $scenario);

        if (! $this->roleplay->canStart($user, $scenario)) {
            return $this->flashBack('warning', __('You have used all :n attempts for this scenario.', ['n' => $scenario->attempts_allowed]));
        }

        try {
            $attempt = $this->roleplay->start($user, $scenario, $lesson, $block);
        } catch (AiLimitReached $exception) {
            return $this->flashBack('error', $exception->getMessage());
        }

        return to_route('learn.roleplay.attempt', ['attempt' => $attempt->id]);
    }

    public function show(Request $request, RoleplayAttempt $attempt): Response
    {
        $user = $this->learner($request);
        $this->assertOwned($user, $attempt);

        return Inertia::render('employee/roleplay/Chat', $this->chatPayload($attempt));
    }

    public function message(RoleplayMessageRequest $request, RoleplayAttempt $attempt): RedirectResponse
    {
        $user = $this->learner($request);
        $this->assertOwned($user, $attempt);

        if (! $attempt->status->acceptsTurns()) {
            return to_route('learn.roleplay.attempt', ['attempt' => $attempt->id]);
        }

        try {
            $ended = $this->roleplay->message($user, $attempt, $request->text(), $request->recordingMediaId());
        } catch (AiLimitReached $exception) {
            return $this->flashBack('error', $exception->getMessage());
        }

        if ($ended) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('The guest has wrapped up the conversation. Here is your feedback.')]);

            return to_route('learn.roleplay.feedback', ['attempt' => $attempt->id]);
        }

        return to_route('learn.roleplay.attempt', ['attempt' => $attempt->id]);
    }

    public function end(Request $request, RoleplayAttempt $attempt): RedirectResponse
    {
        $user = $this->learner($request);
        $this->assertOwned($user, $attempt);

        if ($attempt->status->acceptsTurns()) {
            $this->roleplay->end($attempt);
        }

        return to_route('learn.roleplay.feedback', ['attempt' => $attempt->id]);
    }

    public function feedback(Request $request, RoleplayAttempt $attempt): Response
    {
        $user = $this->learner($request);
        $this->assertOwned($user, $attempt);

        return Inertia::render('employee/roleplay/Feedback', $this->feedbackPayload($attempt, $user));
    }

    /**
     * @return array<string, mixed>
     */
    private function brief(AiScenario $scenario, User $user): array
    {
        $thumbnail = $scenario->thumbnail_media_id !== null
            ? MediaAsset::query()->find($scenario->thumbnail_media_id)
            : null;

        return [
            'id' => $scenario->id,
            'title' => $scenario->title,
            'description' => $scenario->description,
            'difficulty' => $scenario->difficulty->value,
            'icon' => $scenario->icon,
            'thumbnail' => $this->resolver->media($thumbnail),
            'situation' => $scenario->situation,
            'yourRole' => $scenario->employee_role,
            'guestRole' => $scenario->ai_role,
            'objective' => $scenario->objective,
            'goals' => $scenario->goals ?? [],
            'usefulPhrases' => $scenario->useful_phrases ?? [],
            'tip' => $scenario->tip,
            'quote' => $scenario->quote,
            'attemptsAllowed' => $scenario->attempts_allowed,
            'attemptsUsed' => $this->roleplay->attemptsUsed($user, $scenario),
            'attemptsLeft' => $this->roleplay->attemptsLeft($user, $scenario),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function chatPayload(RoleplayAttempt $attempt): array
    {
        $scenario = $attempt->scenario()->firstOrFail();

        return [
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status->value,
                'pendingReply' => $attempt->pending_reply,
                'aiStatus' => $attempt->ai_status?->value,
                'failedReason' => $attempt->failed_reason,
                'transcript' => $this->transcript($attempt),
                'employeeTurns' => $attempt->employeeTurnsCount(),
                'minTurns' => $scenario->min_turns,
                'maxTurns' => $scenario->max_turns,
                // The guest has closed and the next step is feedback (spec
                // 0005 §2.2); the chat swaps the input for that action.
                'atTurnLimit' => $this->roleplay->reachedTurnLimit($attempt),
                'inputMode' => $scenario->input_mode,
                'canEnd' => $attempt->employeeTurnsCount() >= $scenario->min_turns,
            ],
            'scenario' => [
                'title' => $scenario->title,
                'icon' => $scenario->icon,
                'usefulPhrases' => $scenario->useful_phrases ?? [],
                'tip' => $scenario->tip,
            ],
            'messageUrl' => route('learn.roleplay.message', ['attempt' => $attempt->id]),
            'endUrl' => route('learn.roleplay.end', ['attempt' => $attempt->id]),
            'feedbackUrl' => route('learn.roleplay.feedback', ['attempt' => $attempt->id]),
            'pollUrl' => route('learn.roleplay.attempt', ['attempt' => $attempt->id]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function feedbackPayload(RoleplayAttempt $attempt, User $user): array
    {
        $scenario = $attempt->scenario()->firstOrFail();

        return [
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status->value,
                'aiStatus' => $attempt->ai_status?->value,
                'overallScore' => $attempt->overall_score,
                'criteriaScores' => $attempt->criteria_scores ?? [],
                'feedback' => $attempt->feedback,
                'transcript' => $this->transcript($attempt),
            ],
            'scenario' => [
                'title' => $scenario->title,
                'icon' => $scenario->icon,
                'criteria' => $scenario->feedback_criteria ?? [],
            ],
            'attemptsLeft' => $this->roleplay->attemptsLeft($user, $scenario),
            'retryUrl' => $attempt->lesson_id !== null && $attempt->block_id !== null
                ? route('learn.roleplay.ready', ['lesson' => $attempt->lesson_id, 'block' => $attempt->block_id, 'scenario' => $scenario->id])
                : null,
            'lessonUrl' => $attempt->lesson_id !== null && $attempt->block_id !== null
                ? route('learn.lessons.step', ['lesson' => $attempt->lesson_id, 'block' => $attempt->block_id])
                : route('learn.lessons'),
            'pollUrl' => route('learn.roleplay.feedback', ['attempt' => $attempt->id]),
        ];
    }

    /**
     * @return list<array{id: int, role: string, text: string, audio: array{normal: string|null, slow: string|null}, at: string}>
     */
    private function transcript(RoleplayAttempt $attempt): array
    {
        $audio = $this->resolver->audioForMany(array_map(
            static fn (array $turn): ?string => $turn['role'] === RoleplayAttempt::ROLE_GUEST
                ? $turn['text']
                : null,
            $attempt->transcript,
        ));
        $turns = [];

        foreach ($attempt->transcript as $index => $turn) {
            $text = (string) $turn['text'];
            $turns[] = [
                'id' => $index,
                'role' => $turn['role'],
                'text' => $text,
                'audio' => $audio[$text] ?? ['normal' => null, 'slow' => null],
                'at' => $turn['at'],
            ];
        }

        return $turns;
    }

    private function learner(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function assertOwned(User $user, RoleplayAttempt $attempt): void
    {
        abort_unless($attempt->user_id === $user->id, 403);
    }

    private function assertReach(User $user, AiScenario $scenario): void
    {
        abort_unless(
            $scenario->status === ContentStatus::Published
                && $scenario->department_id === $user->learningDepartmentId()
                && ($scenario->hotel_id === null || $scenario->hotel_id === $user->hotel_id),
            403,
        );
    }

    private function flashBack(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }
}

<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Http\Requests\VoiceAgent\VoiceCallTurnRequest;
use App\Models\AiScenario;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\RoleplayAttempt;
use App\Models\User;
use App\Services\Ai\AiLimitReached;
use App\Services\Learning\RoleplayService;
use App\Services\VoiceAgent\VoiceAgentSessionFactory;
use App\Services\VoiceAgent\VoiceCallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

/**
 * The learner's live spoken role-play (RP-03, RP-05, RP-06, RP-11, AIL-03,
 * RESP-05; spec 0004).
 *
 * `start` opens one attempt and returns a short-lived Deepgram session built
 * on the server; `turn` stores each caption as it happens; `end` queues the
 * same evaluation as the text role-play and lands on its feedback page.
 * Reach is checked on the server: a scenario outside the learner's
 * department or hotel is a 403 however it is requested (ROLE-02, SEC-01).
 */
class VoiceCallController extends Controller
{
    public function __construct(
        private readonly RoleplayService $roleplay,
        private readonly VoiceCallService $calls,
        private readonly VoiceAgentSessionFactory $sessions,
    ) {}

    public function start(Request $request, Lesson $lesson, Block $block, AiScenario $scenario): JsonResponse
    {
        $user = $this->learner($request);
        abort_unless($scenario->isVisibleTo($user), 403);

        if (! $this->roleplay->canStart($user, $scenario)) {
            return response()->json([
                'message' => __('You have used all :n attempts for this scenario.', ['n' => $scenario->attempts_allowed]),
            ], 409);
        }

        try {
            $attempt = $this->calls->start($user, $scenario, $lesson, $block);
        } catch (AiLimitReached $exception) {
            return response()->json(['message' => $exception->getMessage()], 429);
        }

        try {
            $session = $this->sessions->create($attempt, $scenario);
        } catch (RuntimeException $exception) {
            // Nothing was said: the failed call must not cost an attempt.
            $attempt->delete();

            return response()->json(['message' => $exception->getMessage()], 503);
        }

        return response()->json([
            'attemptId' => $attempt->id,
            'turnUrl' => route('learn.roleplay.voice.turn', ['attempt' => $attempt->id]),
            'endUrl' => route('learn.roleplay.voice.end', ['attempt' => $attempt->id]),
            'session' => $session,
        ]);
    }

    public function turn(VoiceCallTurnRequest $request, RoleplayAttempt $attempt): JsonResponse
    {
        $user = $this->learner($request);
        $this->assertOwnedCall($user, $attempt);

        $result = $this->calls->recordTurn($attempt, $request->seq(), $request->role(), $request->content());

        return response()->json($result);
    }

    public function end(Request $request, RoleplayAttempt $attempt): RedirectResponse
    {
        $user = $this->learner($request);
        $this->assertOwnedCall($user, $attempt);

        $outcome = $this->calls->end($attempt);

        if ($outcome === VoiceCallService::OUTCOME_EVALUATING) {
            return to_route('learn.roleplay.feedback', ['attempt' => $attempt->id]);
        }

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => $outcome === VoiceCallService::OUTCOME_DISCARDED
                ? __('The call did not connect, so no attempt was used.')
                : __('The call ended before you spoke, so there is nothing to score.'),
        ]);

        if ($attempt->lesson_id !== null && $attempt->block_id !== null) {
            return to_route('learn.roleplay.ready', [
                'lesson' => $attempt->lesson_id,
                'block' => $attempt->block_id,
                'scenario' => $attempt->ai_scenario_id,
            ]);
        }

        return to_route('learn.lessons');
    }

    private function learner(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function assertOwnedCall(User $user, RoleplayAttempt $attempt): void
    {
        abort_unless(
            $attempt->user_id === $user->id
                && ! $attempt->is_preview
                && $attempt->channel === RoleplayAttempt::CHANNEL_VOICE_CALL,
            403,
        );
    }
}

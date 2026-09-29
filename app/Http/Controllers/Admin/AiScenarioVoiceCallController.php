<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AnswersVoiceReplies;
use App\Http\Controllers\Controller;
use App\Http\Requests\VoiceAgent\VoiceCallTurnRequest;
use App\Http\Requests\VoiceAgent\VoiceReplyRequest;
use App\Models\AiScenario;
use App\Models\RoleplayAttempt;
use App\Models\User;
use App\Services\VoiceAgent\VoiceAgentSessionFactory;
use App\Services\VoiceAgent\VoiceCallService;
use App\Services\VoiceAgent\VoiceReplyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use RuntimeException;

/**
 * "Test voice call" from the scenario editor (RP-13; spec 0004): the same
 * live Deepgram call a learner gets, run against a draft or published
 * scenario and flagged `is_preview`, so it never counts toward an employee
 * and never reaches an export. The employee daily limit is not applied: this
 * is authoring, not an employee chatting (AIL-01..03).
 */
class AiScenarioVoiceCallController extends Controller
{
    use AnswersVoiceReplies;

    public function __construct(
        private readonly VoiceCallService $calls,
        private readonly VoiceAgentSessionFactory $sessions,
    ) {}

    public function start(Request $request, AiScenario $scenario): JsonResponse
    {
        Gate::authorize('update', $scenario);

        $attempt = $this->calls->start($this->admin($request), $scenario, null, null, true);

        try {
            $session = $this->sessions->create($attempt, $scenario);
        } catch (RuntimeException $exception) {
            $attempt->delete();

            return response()->json(['message' => $exception->getMessage()], 503);
        }

        return response()->json([
            'attemptId' => $attempt->id,
            'turnUrl' => route('ai-scenarios.voice-preview.turn', ['attempt' => $attempt->id]),
            'replyUrl' => route('ai-scenarios.voice-preview.reply', ['attempt' => $attempt->id]),
            'endUrl' => route('ai-scenarios.voice-preview.end', ['attempt' => $attempt->id]),
            'session' => $session,
        ]);
    }

    public function reply(VoiceReplyRequest $request, RoleplayAttempt $attempt, VoiceReplyService $replies): JsonResponse
    {
        $this->assertOwnedPreview($request, $attempt);

        return $this->answerVoiceReply($attempt, $request, $replies);
    }

    public function turn(VoiceCallTurnRequest $request, RoleplayAttempt $attempt): JsonResponse
    {
        $this->assertOwnedPreview($request, $attempt);

        return response()->json(
            $this->calls->recordTurn($attempt, $request->seq(), $request->role(), $request->content()),
        );
    }

    public function end(Request $request, RoleplayAttempt $attempt): RedirectResponse
    {
        $this->assertOwnedPreview($request, $attempt);

        $scenarioId = $attempt->ai_scenario_id;
        $outcome = $this->calls->end($attempt);

        if ($outcome === VoiceCallService::OUTCOME_EVALUATING) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('Test call ended. The feedback is being prepared.')]);

            return to_route('ai-scenarios', ['tab' => 'preview', 'preview' => $attempt->id]);
        }

        Inertia::flash('toast', ['type' => 'info', 'message' => __('The test call ended without a conversation to score.')]);

        return to_route('ai-scenarios', ['scenario' => $scenarioId]);
    }

    private function admin(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function assertOwnedPreview(Request $request, RoleplayAttempt $attempt): void
    {
        abort_unless(
            $attempt->is_preview
                && $attempt->channel === RoleplayAttempt::CHANNEL_VOICE_CALL
                && $attempt->user_id === $this->admin($request)->id,
            403,
        );
    }
}

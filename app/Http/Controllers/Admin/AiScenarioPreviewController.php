<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleplayStatus;
use App\Http\Controllers\Controller;
use App\Jobs\EvaluateRoleplayAttempt;
use App\Jobs\GenerateRoleplayReply;
use App\Models\AiScenario;
use App\Models\RoleplayAttempt;
use App\Models\User;
use App\Services\Learning\RoleplayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The Super Admin "Preview & Test" role-play (RP-13): a real conversation
 * against a scenario, run through the same engine as a learner's — Qwen for
 * the guest's turns (GenerateRoleplayReply) and Deepgram for the audio — but
 * flagged `is_preview`, so it never counts toward an employee's attempts and
 * never reaches an export (RP-13, RoleplayAttempt::scopeCounted).
 *
 * The employee daily limit is deliberately not checked here: a preview is the
 * admin authoring content, not an employee chatting (AIL-01..AIL-03).
 *
 * Unlike a learner's conversation, the guest's reply and the evaluation run
 * inside the request: a preview must work, or say why it failed, even when
 * the server's queue worker is stopped (client report 2026-09-30, "Test
 * this scenario" sat on "The guest is replying…" forever).
 */
class AiScenarioPreviewController extends Controller
{
    public function __construct(private readonly RoleplayService $roleplay) {}

    /**
     * Open a preview conversation and queue the guest's opening line.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'scenario' => ['required', 'integer', Rule::exists('ai_scenarios', 'id')],
        ]);

        $scenario = AiScenario::query()->whereKey((int) $validated['scenario'])->firstOrFail();
        Gate::authorize('update', $scenario);

        $attempt = RoleplayAttempt::query()->create([
            'user_id' => $this->admin($request)->id,
            'ai_scenario_id' => $scenario->id,
            'attempt_no' => 1,
            'status' => RoleplayStatus::InProgress,
            'transcript' => [],
            'pending_reply' => true,
            'is_preview' => true,
            'started_at' => Date::now(),
        ]);

        $this->runNow(new GenerateRoleplayReply($attempt->id));

        return $this->backToPreview($request, $attempt);
    }

    /**
     * Append the admin's line and queue the guest's reply.
     */
    public function message(Request $request, RoleplayAttempt $attempt): RedirectResponse
    {
        $this->assertOwnedPreview($request, $attempt);

        if (! $attempt->status->acceptsTurns() || $attempt->pending_reply) {
            return $this->backToPreview($request, $attempt);
        }

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
        ]);

        $attempt->appendTurn(RoleplayAttempt::ROLE_EMPLOYEE, $validated['text']);
        $attempt->forceFill(['pending_reply' => true])->save();

        $this->runNow(new GenerateRoleplayReply($attempt->id));

        return $this->backToPreview($request, $attempt);
    }

    /**
     * End the preview and queue its evaluation (RP-07).
     */
    public function end(Request $request, RoleplayAttempt $attempt): RedirectResponse
    {
        $this->assertOwnedPreview($request, $attempt);

        if ($attempt->status->acceptsTurns()) {
            $this->roleplay->end($attempt, queueEvaluation: false);
            $this->runNow(new EvaluateRoleplayAttempt($attempt->id));
        }

        return $this->backToPreview($request, $attempt);
    }

    /**
     * Discard the preview and start fresh. Nothing is exported, so the row is
     * safe to delete (RP-13, DATA-10 applies only to real attempts).
     */
    public function destroy(Request $request, RoleplayAttempt $attempt): RedirectResponse
    {
        $this->assertOwnedPreview($request, $attempt);

        $scenarioId = $attempt->ai_scenario_id;
        $attempt->delete();

        return $request->boolean('editor')
            ? to_route('ai-scenarios', ['tab' => 'scenarios', 'scenario' => $scenarioId, 'section' => 'test'])
            : to_route('ai-scenarios', ['tab' => 'preview']);
    }

    /**
     * Run an AI job now; on failure write the job's own failed state, which
     * the Test panel shows with its reason (PERF-04).
     */
    private function runNow(GenerateRoleplayReply|EvaluateRoleplayAttempt $job): void
    {
        @set_time_limit(180);

        try {
            app()->call([$job, 'handle']);
        } catch (Throwable $exception) {
            report($exception);
            $job->failed($exception);
        }
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
            $attempt->is_preview && $attempt->user_id === $this->admin($request)->id,
            403,
        );
    }

    /**
     * A test started from the scenario editor's Test tab stays in the
     * editor (client request 2026-09-30); the Preview & Test page otherwise.
     */
    private function backToPreview(Request $request, RoleplayAttempt $attempt): RedirectResponse
    {
        return $request->boolean('editor')
            ? to_route('ai-scenarios', ['tab' => 'scenarios', 'scenario' => $attempt->ai_scenario_id, 'section' => 'test', 'preview' => $attempt->id])
            : to_route('ai-scenarios', ['tab' => 'preview', 'preview' => $attempt->id]);
    }
}

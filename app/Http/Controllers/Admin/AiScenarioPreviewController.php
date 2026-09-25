<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleplayStatus;
use App\Http\Controllers\Controller;
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

/**
 * The Super Admin "Preview & Test" role-play (RP-13): a real conversation
 * against a scenario, run through the same engine as a learner's — Qwen for
 * the guest's turns (GenerateRoleplayReply) and Deepgram for the audio — but
 * flagged `is_preview`, so it never counts toward an employee's attempts and
 * never reaches an export (RP-13, RoleplayAttempt::scopeCounted).
 *
 * The employee daily limit is deliberately not checked here: a preview is the
 * admin authoring content, not an employee chatting (AIL-01..AIL-03).
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

        GenerateRoleplayReply::dispatch($attempt->id);

        return $this->backToPreview($attempt);
    }

    /**
     * Append the admin's line and queue the guest's reply.
     */
    public function message(Request $request, RoleplayAttempt $attempt): RedirectResponse
    {
        $this->assertOwnedPreview($request, $attempt);

        if (! $attempt->status->acceptsTurns() || $attempt->pending_reply) {
            return $this->backToPreview($attempt);
        }

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
        ]);

        $attempt->appendTurn(RoleplayAttempt::ROLE_EMPLOYEE, $validated['text']);
        $attempt->forceFill(['pending_reply' => true])->save();

        GenerateRoleplayReply::dispatch($attempt->id);

        return $this->backToPreview($attempt);
    }

    /**
     * End the preview and queue its evaluation (RP-07).
     */
    public function end(Request $request, RoleplayAttempt $attempt): RedirectResponse
    {
        $this->assertOwnedPreview($request, $attempt);

        if ($attempt->status->acceptsTurns()) {
            $this->roleplay->end($attempt);
        }

        return $this->backToPreview($attempt);
    }

    /**
     * Discard the preview and start fresh. Nothing is exported, so the row is
     * safe to delete (RP-13, DATA-10 applies only to real attempts).
     */
    public function destroy(Request $request, RoleplayAttempt $attempt): RedirectResponse
    {
        $this->assertOwnedPreview($request, $attempt);

        $attempt->delete();

        return to_route('ai-scenarios', ['tab' => 'preview']);
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

    private function backToPreview(RoleplayAttempt $attempt): RedirectResponse
    {
        return to_route('ai-scenarios', ['tab' => 'preview', 'preview' => $attempt->id]);
    }
}

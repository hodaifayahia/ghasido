<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\OverrideScoreRequest;
use App\Models\Attempt;
use App\Models\RoleplayAttempt;
use App\Models\User;
use App\Services\Reports\ScoreOverrides;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use InvalidArgumentException;

/**
 * Adjusting an AI score from Reports & Export (AIE-05; spec 0005 §2.5):
 * replace it with a reason, restore the machine's value, or ask the AI to
 * grade again. Every action is authorized by the attempt policy's
 * `overrideScore` ability and audit-logged by the service (SEC-06).
 */
class ScoreOverrideController extends Controller
{
    public function __construct(private readonly ScoreOverrides $overrides) {}

    public function updateAnswer(OverrideScoreRequest $request, Attempt $attempt): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return $this->run(
            fn () => $this->overrides->overrideAnswer($attempt, $request->score(), $request->reason(), $actor),
            __('Score updated. The original score is kept alongside it.'),
        );
    }

    public function restoreAnswer(Attempt $attempt): RedirectResponse
    {
        Gate::authorize('overrideScore', $attempt);

        return $this->run(
            fn () => $this->overrides->restoreAnswer($attempt),
            __('The original score is back.'),
        );
    }

    public function reevaluateAnswer(Attempt $attempt): RedirectResponse
    {
        Gate::authorize('overrideScore', $attempt);

        return $this->run(
            fn () => $this->overrides->reevaluateAnswer($attempt),
            __('The AI is grading this answer again. Refresh in a moment to see the result.'),
        );
    }

    public function updateRoleplay(OverrideScoreRequest $request, RoleplayAttempt $roleplayAttempt): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return $this->run(
            fn () => $this->overrides->overrideRoleplay($roleplayAttempt, (int) $request->score(), $request->reason(), $actor),
            __('Score updated. The original score is kept alongside it.'),
        );
    }

    public function restoreRoleplay(RoleplayAttempt $roleplayAttempt): RedirectResponse
    {
        Gate::authorize('overrideScore', $roleplayAttempt);

        return $this->run(
            fn () => $this->overrides->restoreRoleplay($roleplayAttempt),
            __('The original score is back.'),
        );
    }

    public function reevaluateRoleplay(RoleplayAttempt $roleplayAttempt): RedirectResponse
    {
        Gate::authorize('overrideScore', $roleplayAttempt);

        return $this->run(
            fn () => $this->overrides->reevaluateRoleplay($roleplayAttempt),
            __('The AI is grading this conversation again. Refresh in a moment to see the result.'),
        );
    }

    /**
     * @param  callable(): void  $action
     */
    private function run(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidArgumentException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $success]);

        return back();
    }
}

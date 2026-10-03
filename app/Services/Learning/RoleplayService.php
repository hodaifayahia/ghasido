<?php

namespace App\Services\Learning;

use App\Enums\AiFeature;
use App\Enums\RoleplayStatus;
use App\Jobs\EvaluateRoleplayAttempt;
use App\Jobs\GenerateRoleplayReply;
use App\Models\AiScenario;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\RoleplayAttempt;
use App\Models\User;
use App\Services\Ai\AiLimitReached;
use App\Services\Ai\UsageMeter;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * AI role-play conversations (RP-01..11, AIE-04, AIL-03, DATA-05; spec 0003
 * Part E, Part C).
 *
 * One attempt is one whole conversation, capped by the scenario's
 * `attempts_allowed` (RP-05, RP-06). The daily AI limit is checked before a
 * turn is ever dispatched, never before evaluation, so a finished
 * conversation can always be scored (AIL-03). Every turn is appended to the
 * stored transcript in full (RP-11, DATA-05).
 */
class RoleplayService
{
    public function __construct(
        private readonly UsageMeter $meter,
        private readonly ProgressService $progress,
    ) {}

    public function attemptsUsed(User $user, AiScenario $scenario): int
    {
        return RoleplayAttempt::query()
            ->counted()
            ->where('user_id', $user->id)
            ->where('ai_scenario_id', $scenario->id)
            ->count();
    }

    public function attemptsLeft(User $user, AiScenario $scenario): int
    {
        return max(0, $scenario->attempts_allowed - $this->attemptsUsed($user, $scenario));
    }

    public function canStart(User $user, AiScenario $scenario): bool
    {
        return $this->attemptsLeft($user, $scenario) > 0;
    }

    /**
     * Open a conversation and queue the guest's opening line (RP-03, RP-05).
     *
     * @throws AiLimitReached
     */
    public function start(User $user, AiScenario $scenario, ?Lesson $lesson, ?Block $block): RoleplayAttempt
    {
        $this->meter->assertWithinLimits($user, AiFeature::RoleplayTurn);

        $attempt = RoleplayAttempt::query()->create([
            'user_id' => $user->id,
            'ai_scenario_id' => $scenario->id,
            'lesson_id' => $lesson?->id,
            'block_id' => $block?->id,
            'attempt_no' => $this->attemptsUsed($user, $scenario) + 1,
            'status' => RoleplayStatus::InProgress,
            'transcript' => [],
            'pending_reply' => true,
            'is_preview' => false,
            'started_at' => Date::now(),
        ]);

        // A conversation is learning activity like a lesson step (DATA-06;
        // spec 0005 §5.4), so the streak, the at-risk list and the
        // inactivity reminder all read the same fact.
        $this->progress->touch($user);

        self::replySoon($attempt->id);

        return $attempt;
    }

    /**
     * Append the employee's turn and queue the guest's reply (RP-03).
     *
     * @throws AiLimitReached
     */
    public function message(User $user, RoleplayAttempt $attempt, string $text, ?int $audioMediaId): bool
    {
        // The scenario's bound is enforced here, not only suggested to the
        // model (spec 0005 §2.2): once the employee has replied max_turns
        // times the guest has closed, and the conversation goes to feedback
        // instead of costing another turn. Returns true when it ended.
        if ($this->reachedTurnLimit($attempt)) {
            $this->end($attempt);

            return true;
        }

        $this->meter->assertWithinLimits($user, AiFeature::RoleplayTurn);

        $attempt->appendTurn(RoleplayAttempt::ROLE_EMPLOYEE, $text, $audioMediaId);
        $attempt->forceFill(['pending_reply' => true])->save();
        $this->progress->touch($user);

        self::replySoon($attempt->id);

        return false;
    }

    /**
     * Has the employee used every reply the scenario allows?
     */
    public function reachedTurnLimit(RoleplayAttempt $attempt): bool
    {
        $scenario = $attempt->scenario;

        return $scenario !== null && $attempt->employeeTurnsCount() >= max(1, $scenario->max_turns);
    }

    /**
     * End the conversation and queue its evaluation (RP-07). The limit is not
     * checked here — a conversation that happened must always be scored.
     */
    public function end(RoleplayAttempt $attempt, ?CarbonInterface $endedAt = null, bool $queueEvaluation = true): void
    {
        // A call closed after the fact (its page went away) ends when it
        // was last heard, not now, so its length is billed as it happened.
        $endedAt ??= Date::now();

        $attempt->forceFill([
            'status' => RoleplayStatus::Evaluating,
            'ended_at' => $endedAt,
            'duration_ms' => (int) abs($attempt->started_at->diffInMilliseconds($endedAt)),
        ])->save();

        // The admin preview scores inline instead (AiScenarioPreviewController).
        if ($queueEvaluation) {
            // Right after the response, like the reply: feedback never waits
            // on a queue worker the host may not run.
            EvaluateRoleplayAttempt::dispatchAfterResponse($attempt->id);
        }
    }

    /**
     * The guest's next written line, written right after the response
     * instead of on the queue (client report 2026-10-02: "writing does not
     * work, only speaking"): voice replies were already answered inside the
     * request, but typed ones waited for a queue worker the host may not
     * run. The chat page polls `pending_reply` as before (PERF-04).
     */
    public static function replySoon(int $attemptId): void
    {
        @set_time_limit(180);

        // On the sync queue (tests, a console call) there is no response to
        // wait for: run it now, or it would never run at all.
        if (config('queue.default') === 'sync') {
            GenerateRoleplayReply::dispatch($attemptId);

            return;
        }

        GenerateRoleplayReply::dispatchAfterResponse($attemptId);
    }
}

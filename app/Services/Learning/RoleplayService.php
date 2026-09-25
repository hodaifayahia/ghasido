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
    public function __construct(private readonly UsageMeter $meter) {}

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

        GenerateRoleplayReply::dispatch($attempt->id);

        return $attempt;
    }

    /**
     * Append the employee's turn and queue the guest's reply (RP-03).
     *
     * @throws AiLimitReached
     */
    public function message(User $user, RoleplayAttempt $attempt, string $text, ?int $audioMediaId): void
    {
        $this->meter->assertWithinLimits($user, AiFeature::RoleplayTurn);

        $attempt->appendTurn(RoleplayAttempt::ROLE_EMPLOYEE, $text, $audioMediaId);
        $attempt->forceFill(['pending_reply' => true])->save();

        GenerateRoleplayReply::dispatch($attempt->id);
    }

    /**
     * End the conversation and queue its evaluation (RP-07). The limit is not
     * checked here — a conversation that happened must always be scored.
     */
    public function end(RoleplayAttempt $attempt): void
    {
        $attempt->forceFill([
            'status' => RoleplayStatus::Evaluating,
            'ended_at' => Date::now(),
            'duration_ms' => (int) $attempt->started_at->diffInMilliseconds(Date::now()),
        ])->save();

        EvaluateRoleplayAttempt::dispatch($attempt->id);
    }
}

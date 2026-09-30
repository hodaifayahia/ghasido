<?php

namespace App\Services\VoiceAgent;

use App\Contracts\AiUsageInfo;
use App\Enums\AiFeature;
use App\Enums\ApiAccount;
use App\Enums\RoleplayStatus;
use App\Models\AiScenario;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\RoleplayAttempt;
use App\Models\User;
use App\Services\Ai\AiLimitReached;
use App\Services\Ai\UsageMeter;
use App\Services\Learning\ProgressService;
use App\Services\Learning\RoleplayService;
use App\Services\Owner\ApiCredit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * One live voice call = one role-play attempt (RP-05, RP-06, RP-11, RP-13,
 * AIL-03; spec 0004).
 *
 * The browser posts every caption Deepgram emits; each is appended to the
 * stored transcript server-side, keyed by the client's sequence number so a
 * retried POST never stores a line twice and a late one still lands in
 * order (PROG-04, DATA-05). Only the text is kept: no audio, no video
 * (spec 0004 decision 3).
 */
final class VoiceCallService
{
    public const OUTCOME_EVALUATING = 'evaluating';

    public const OUTCOME_ABANDONED = 'abandoned';

    public const OUTCOME_DISCARDED = 'discarded';

    /** A live call with no caption for this long has lost its page. */
    public const int STALE_AFTER_MINUTES = 30;

    public function __construct(
        private readonly UsageMeter $meter,
        private readonly RoleplayService $roleplay,
        private readonly VoiceAgentSettings $settings,
        private readonly ProgressService $progress,
    ) {}

    /**
     * @throws AiLimitReached for a learner past today's AI allowance
     */
    public function start(User $user, AiScenario $scenario, ?Lesson $lesson, ?Block $block, bool $preview = false): RoleplayAttempt
    {
        // An earlier call of theirs that never hung up is closed and billed
        // first, so its minutes and points are not lost (API-03, AIL-01).
        $this->closeStale($user);

        $values = $this->settings->forScenario($scenario);

        // The owner's Deepgram (and Qwen, for the Qwen proxy and the fast
        // engine) credit, for a preview too: it spends the same accounts
        // (spec 0007, D7a).
        $this->meter->assertVoiceCredit($user, $values['engine'] === 'pipeline' || $values['thinkMode'] === 'qwen_proxy');

        if (! $preview) {
            $this->meter->assertWithinLimits($user, AiFeature::RoleplayTurn);
            $this->meter->assertVoicePointsAvailable($user);
        }

        $attempt = RoleplayAttempt::query()->create([
            'user_id' => $user->id,
            'ai_scenario_id' => $scenario->id,
            'lesson_id' => $lesson?->id,
            'block_id' => $block?->id,
            'attempt_no' => $preview ? 1 : $this->roleplay->attemptsUsed($user, $scenario) + 1,
            'status' => RoleplayStatus::InProgress,
            'transcript' => [],
            'pending_reply' => false,
            'is_preview' => $preview,
            'channel' => RoleplayAttempt::CHANNEL_VOICE_CALL,
            'voice_engine' => $values['engine'],
            'started_at' => Date::now(),
        ]);

        if (! $preview) {
            // A call is learning activity like a lesson step (DATA-06; spec
            // 0005 §5.4); an admin's preview is not.
            $this->progress->touch($user);
        }

        return $attempt;
    }

    /**
     * Append one caption. `role` is Deepgram's: `user` (the employee) or
     * `assistant`/`agent` (the guest).
     *
     * @return array{stored: bool, limitReached: bool}
     */
    public function recordTurn(RoleplayAttempt $attempt, int $seq, string $role, string $content): array
    {
        $mapped = $role === 'user' ? RoleplayAttempt::ROLE_EMPLOYEE : RoleplayAttempt::ROLE_GUEST;
        $text = trim($content);

        $stored = DB::transaction(function () use ($attempt, $seq, $mapped, $text): bool {
            /** @var RoleplayAttempt $locked */
            $locked = RoleplayAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->acceptsTurns() || $text === '') {
                return false;
            }

            $turns = $locked->transcript;

            foreach ($turns as $turn) {
                if (($turn['seq'] ?? null) === $seq) {
                    return false;
                }
            }

            $turns[] = [
                'role' => $mapped,
                'text' => $text,
                'seq' => $seq,
                'at' => Date::now()->toIso8601String(),
            ];

            // A late request still lands in the order the call happened.
            usort($turns, static fn (array $a, array $b): int => ($a['seq'] ?? PHP_INT_MAX) <=> ($b['seq'] ?? PHP_INT_MAX));

            $locked->transcript = $turns;
            $locked->save();

            return true;
        });

        $attempt->refresh();
        $user = $attempt->user;

        if ($stored && $mapped === RoleplayAttempt::ROLE_GUEST && $user !== null) {
            $scenario = $attempt->scenario;
            $values = $scenario !== null ? $this->settings->forScenario($scenario) : $this->settings->current();

            // With Deepgram's managed LLM each guest line is one metered turn
            // (AIL-04). The Qwen proxy and the fast engine meter their own
            // real token usage.
            if ($values['thinkMode'] === 'managed' && $attempt->voice_engine !== 'pipeline') {
                $this->meter->record($user, AiFeature::RoleplayTurn, new AiUsageInfo(
                    0,
                    0,
                    $values['thinkModel'],
                    'deepgram_agent_'.$values['thinkProvider'],
                ), chargePoints: false);
            }
        }

        $limitReached = (! $attempt->is_preview
            && $user !== null
            && (! $this->meter->isWithinLimits($user, AiFeature::RoleplayTurn)
                || ! $this->meter->canContinueVoice($attempt)))
            // The owner's Deepgram credit ends any call, preview or not.
            || ! app(ApiCredit::class)->isAvailable(ApiAccount::Deepgram);

        return ['stored' => $stored, 'limitReached' => $limitReached];
    }

    /**
     * Close the call. A call that never connected (no caption at all) is
     * removed so a microphone or network failure does not burn an attempt;
     * a call where the employee never spoke is abandoned; anything else is
     * evaluated (RP-07).
     */
    public function end(RoleplayAttempt $attempt, ?CarbonInterface $endedAt = null): string
    {
        if (! $attempt->status->acceptsTurns()) {
            return $attempt->status === RoleplayStatus::Abandoned ? self::OUTCOME_ABANDONED : self::OUTCOME_EVALUATING;
        }

        if ($attempt->turnsCount() === 0) {
            $attempt->delete();

            return self::OUTCOME_DISCARDED;
        }

        $endedAt ??= Date::now();

        if ($attempt->employeeTurnsCount() === 0) {
            $attempt->forceFill([
                'status' => RoleplayStatus::Abandoned,
                'ended_at' => $endedAt,
                'duration_ms' => (int) abs($attempt->started_at->diffInMilliseconds($endedAt)),
            ])->save();
            $this->meter->chargeVoiceAttempt($attempt);
            $this->meter->recordVoiceCall($attempt);

            return self::OUTCOME_ABANDONED;
        }

        $this->roleplay->end($attempt, $endedAt);
        $this->meter->chargeVoiceAttempt($attempt);
        $this->meter->recordVoiceCall($attempt->refresh());

        return self::OUTCOME_EVALUATING;
    }

    /**
     * Close the live calls whose page went away without hanging up (tab
     * closed, phone locked, network lost). Deepgram billed them all the
     * same, so they are ended as of their last caption and metered and
     * charged like any other call (API-03, AIL-01; spec 0007, D9). Each
     * call is closed once: an ended call no longer accepts turns.
     *
     * @return int the calls closed
     */
    public function closeStale(?User $user = null, int $idleMinutes = self::STALE_AFTER_MINUTES): int
    {
        $closed = 0;

        RoleplayAttempt::query()
            ->where('channel', RoleplayAttempt::CHANNEL_VOICE_CALL)
            ->where('status', RoleplayStatus::InProgress->value)
            ->where('updated_at', '<', Date::now()->subMinutes($idleMinutes))
            ->when($user !== null, fn (Builder $query) => $query->where('user_id', $user?->id))
            ->orderBy('id')
            ->get()
            ->each(function (RoleplayAttempt $attempt) use (&$closed): void {
                // Re-read under a lock: the learner's own hang-up, or another
                // sweep, may have closed it meanwhile, and a call is billed once.
                DB::transaction(function () use ($attempt, &$closed): void {
                    $locked = RoleplayAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();

                    if ($locked === null || ! $locked->status->acceptsTurns()) {
                        return;
                    }

                    $this->end($locked, $locked->updated_at ?? $locked->started_at);
                    $closed++;
                });
            });

        return $closed;
    }
}

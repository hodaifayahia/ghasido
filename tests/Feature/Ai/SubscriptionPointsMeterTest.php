<?php

namespace Tests\Feature\Ai;

use App\Contracts\AiUsageInfo;
use App\Enums\AiFeature;
use App\Models\Hotel;
use App\Models\RoleplayAttempt;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Ai\AiLimitReached;
use App\Services\Ai\UsageMeter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

/** Per-action and voice-block point billing (AIL-01, AIL-03, AIL-04). */
class SubscriptionPointsMeterTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_employee_ai_actions_use_the_plan_rate_and_managers_are_not_charged(): void
    {
        $hotel = Hotel::factory()->create();
        $employee = User::factory()->employee()->create(['hotel_id' => $hotel->id]);
        $manager = User::factory()->manager()->create(['hotel_id' => $hotel->id]);
        $meter = new UsageMeter;

        $action = $meter->record($employee, AiFeature::WritingEval, AiUsageInfo::none());
        $managerAction = $meter->record($manager, AiFeature::WritingEval, AiUsageInfo::none());

        $this->assertSame(50, $action->points_charged);
        $this->assertSame(0, $managerAction->points_charged);
        $this->assertSame(1950, $meter->availablePoints($employee));

        SubscriptionPlan::query()->whereKey($hotel->subscription_plan_id)->update(['ai_action_points' => 75]);
        $customRateAction = $meter->record($employee, AiFeature::WritingEval, AiUsageInfo::none());
        $this->assertSame(75, $customRateAction->points_charged);
        $this->assertSame(1875, $meter->availablePoints($employee));
    }

    public function test_an_employee_cannot_start_an_ai_action_without_enough_allocated_points(): void
    {
        $hotel = Hotel::factory()->create();
        $employee = User::factory()->employee()->create([
            'hotel_id' => $hotel->id,
            'ai_points_allocated' => 50,
        ]);
        $meter = new UsageMeter;

        $meter->record($employee, AiFeature::WritingEval, AiUsageInfo::none());
        $this->assertFalse($meter->isWithinLimits($employee, AiFeature::RoleplayTurn));

        try {
            $meter->assertWithinLimits($employee, AiFeature::RoleplayTurn);
            $this->fail('An employee with no remaining points was allowed to start an AI action.');
        } catch (AiLimitReached $exception) {
            $this->assertSame('employee', $exception->scope);
            $this->assertSame(50, $exception->limit);
            $this->assertStringContainsString('0 AI points remaining', $exception->getMessage());
        }
    }

    public function test_voice_calls_are_charged_in_rounded_up_ten_minute_blocks_once(): void
    {
        $hotel = Hotel::factory()->create();
        $employee = User::factory()->employee()->create(['hotel_id' => $hotel->id]);
        $attempt = RoleplayAttempt::factory()->create([
            'user_id' => $employee->id,
            'duration_ms' => 600001,
        ]);
        $meter = new UsageMeter;

        $meter->chargeVoiceAttempt($attempt);
        $this->assertSame(200, $attempt->fresh()->ai_points_charged);
        $this->assertSame(1800, $meter->availablePoints($employee));

        $meter->chargeVoiceAttempt($attempt->fresh());
        $this->assertSame(200, $attempt->fresh()->ai_points_charged);
    }

    public function test_voice_rate_is_configurable_and_employee_must_have_a_full_block_available(): void
    {
        Date::setTestNow(Date::parse('2026-09-23 12:00:00'));
        $hotel = Hotel::factory()->create();
        $plan = SubscriptionPlan::query()->findOrFail($hotel->subscription_plan_id);
        $plan->update(['voice_points_per_10_minutes' => 125]);
        $employee = User::factory()->employee()->create([
            'hotel_id' => $hotel->id,
            'ai_points_allocated' => 124,
        ]);
        $meter = new UsageMeter;

        try {
            $meter->assertVoicePointsAvailable($employee);
            $this->fail('A voice call started without enough points for its first ten-minute block.');
        } catch (AiLimitReached $exception) {
            $this->assertSame('employee', $exception->scope);
            $this->assertSame(125, $exception->limit);
            $this->assertStringContainsString('124 AI points remaining', $exception->getMessage());
        }

        $attempt = RoleplayAttempt::factory()->create([
            'user_id' => $employee->id,
            'started_at' => now(),
        ]);
        $this->assertFalse($meter->canContinueVoice($attempt));

        $employee->forceFill(['ai_points_allocated' => 250])->save();
        $this->assertSame(250, $employee->fresh()->ai_points_allocated);
        $this->assertSame(250, $meter->availablePoints($employee->fresh()));
        $this->assertSame(0, $meter->turnsUsedTodayBy($employee->fresh()));
        $freshAttempt = $attempt->fresh();
        $this->assertSame($employee->id, $freshAttempt->user->id);
        $this->assertSame(125, $freshAttempt->user->hotel->subscriptionPlan->voice_points_per_10_minutes);
        $elapsedMilliseconds = abs($freshAttempt->started_at->diffInMilliseconds(Date::now()));
        $this->assertLessThan(600000, $elapsedMilliseconds);
        $this->assertTrue($meter->canContinueVoice($freshAttempt));
    }

    public function test_preview_and_zero_duration_voice_attempts_do_not_consume_points(): void
    {
        $hotel = Hotel::factory()->create();
        $employee = User::factory()->employee()->create(['hotel_id' => $hotel->id]);
        $preview = RoleplayAttempt::factory()->preview()->create([
            'user_id' => $employee->id,
            'duration_ms' => 600000,
        ]);
        $zeroDuration = RoleplayAttempt::factory()->create([
            'user_id' => $employee->id,
            'duration_ms' => 0,
        ]);
        $meter = new UsageMeter;

        $meter->chargeVoiceAttempt($preview);
        $meter->chargeVoiceAttempt($zeroDuration);

        $this->assertSame(0, $preview->fresh()->ai_points_charged);
        $this->assertSame(0, $zeroDuration->fresh()->ai_points_charged);
        $this->assertSame(2000, $meter->availablePoints($employee));
    }

    public function test_a_custom_action_rate_is_used_to_block_an_expensive_ai_action(): void
    {
        $hotel = Hotel::factory()->create();
        SubscriptionPlan::query()->whereKey($hotel->subscription_plan_id)->update(['ai_action_points' => 75]);
        $employee = User::factory()->employee()->create([
            'hotel_id' => $hotel->id,
            'ai_points_allocated' => 74,
        ]);
        $meter = new UsageMeter;

        try {
            $meter->assertWithinLimits($employee, AiFeature::RoleplayTurn);
            $this->fail('The action was not blocked when the allocation was below its configured cost.');
        } catch (AiLimitReached $exception) {
            $this->assertSame(75, $exception->limit);
            $this->assertStringContainsString('74 AI points remaining', $exception->getMessage());
        }
    }
}

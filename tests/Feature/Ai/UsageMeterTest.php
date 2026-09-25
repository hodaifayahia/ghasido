<?php

namespace Tests\Feature\Ai;

use App\Contracts\AiUsageInfo;
use App\Enums\AiFeature;
use App\Models\AiUsage;
use App\Models\Hotel;
use App\Models\User;
use App\Services\Ai\AiLimitReached;
use App\Services\Ai\UsageMeter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

/**
 * Metering and the daily limits (AIL-01..AIL-04, API-03; spec 0003 Part C).
 *
 * Limits are counted live from ai_usages, never cached on the user, and
 * only role-play turns spend them.
 */
class UsageMeterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'guesvia.ai.limits.per_employee_daily_turns' => 2,
            'guesvia.ai.limits.per_hotel_daily_turns' => 3,
        ]);
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_record_writes_one_ledger_row_per_call()
    {
        $user = $this->employee();

        $row = (new UsageMeter)->record(
            $user,
            AiFeature::RoleplayEval,
            new AiUsageInfo(promptTokens: 120, completionTokens: 45, model: 'test-model', provider: 'anthropic'),
            0.0125,
        );

        $this->assertDatabaseHas('ai_usages', [
            'id' => $row->id,
            'user_id' => $user->id,
            'hotel_id' => $user->hotel_id,
            'feature' => 'roleplay_eval',
            'provider' => 'anthropic',
            'model' => 'test-model',
            'prompt_tokens' => 120,
            'completion_tokens' => 45,
        ]);
        $this->assertSame(165, $row->totalTokens());
        $this->assertEqualsWithDelta(0.0125, (float) $row->cost_estimate, 0.000001);
        $this->assertNotNull($row->occurred_at);
    }

    public function test_a_call_with_no_user_is_still_metered()
    {
        $row = (new UsageMeter)->record(null, AiFeature::Tts, AiUsageInfo::none());

        $this->assertNull($row->user_id);
        $this->assertNull($row->hotel_id);
        $this->assertSame(AiFeature::Tts, $row->feature);
    }

    public function test_an_employee_under_the_limit_passes()
    {
        $meter = new UsageMeter;
        $user = $this->employee();

        $meter->record($user, AiFeature::RoleplayTurn, AiUsageInfo::none());

        $meter->assertWithinLimits($user, AiFeature::RoleplayTurn);
        $this->assertTrue($meter->isWithinLimits($user, AiFeature::RoleplayTurn));
        $this->assertSame(1, $meter->turnsUsedTodayBy($user));
    }

    public function test_the_employee_limit_reached_throws_with_a_user_facing_message()
    {
        $meter = new UsageMeter;
        $user = $this->employee();

        $meter->record($user, AiFeature::RoleplayTurn, AiUsageInfo::none());
        $meter->record($user, AiFeature::RoleplayTurn, AiUsageInfo::none());

        $this->assertFalse($meter->isWithinLimits($user, AiFeature::RoleplayTurn));

        try {
            $meter->assertWithinLimits($user, AiFeature::RoleplayTurn);
            $this->fail('AiLimitReached was not thrown.');
        } catch (AiLimitReached $e) {
            $this->assertSame('employee', $e->scope);
            $this->assertSame(2, $e->limit);
            $this->assertSame(AiFeature::RoleplayTurn, $e->feature);
            $this->assertStringContainsString('2 turns', $e->getMessage());
            // The rest of the platform stays usable (AIL-03).
            $this->assertStringContainsString('lessons', $e->getMessage());
        }
    }

    public function test_the_hotel_limit_reached_blocks_a_colleague_who_has_not_spent_a_turn()
    {
        $meter = new UsageMeter;
        $hotel = Hotel::factory()->create();
        $first = $this->employee($hotel);
        $second = $this->employee($hotel);
        $third = $this->employee($hotel);
        $elsewhere = $this->employee();

        $meter->record($first, AiFeature::RoleplayTurn, AiUsageInfo::none());
        $meter->record($first, AiFeature::RoleplayTurn, AiUsageInfo::none());
        $meter->record($second, AiFeature::RoleplayTurn, AiUsageInfo::none());

        $this->assertSame(3, $meter->turnsUsedTodayByHotel($hotel->id));

        try {
            $meter->assertWithinLimits($third, AiFeature::RoleplayTurn);
            $this->fail('AiLimitReached was not thrown.');
        } catch (AiLimitReached $e) {
            $this->assertSame('hotel', $e->scope);
            $this->assertSame(3, $e->limit);
        }

        // Another hotel's employee is unaffected.
        $meter->assertWithinLimits($elsewhere, AiFeature::RoleplayTurn);
        $this->assertTrue(true);
    }

    public function test_only_role_play_turns_spend_the_limit()
    {
        $meter = new UsageMeter;
        $user = $this->employee();

        foreach ([AiFeature::RoleplayEval, AiFeature::WritingEval, AiFeature::LexiconGenerate, AiFeature::Tts, AiFeature::Stt] as $feature) {
            $meter->record($user, $feature, AiUsageInfo::none());
            $meter->record($user, $feature, AiUsageInfo::none());
        }

        $this->assertSame(0, $meter->turnsUsedTodayBy($user));
        $meter->assertWithinLimits($user, AiFeature::RoleplayTurn);
        $this->assertTrue(true);
    }

    public function test_yesterdays_turns_do_not_count_today()
    {
        $meter = new UsageMeter;
        $user = $this->employee();

        Date::setTestNow(Date::parse('2026-09-17 23:30:00'));
        $meter->record($user, AiFeature::RoleplayTurn, AiUsageInfo::none());
        $meter->record($user, AiFeature::RoleplayTurn, AiUsageInfo::none());

        Date::setTestNow(Date::parse('2026-09-18 00:10:00'));
        $this->assertSame(0, $meter->turnsUsedTodayBy($user));
        $meter->assertWithinLimits($user, AiFeature::RoleplayTurn);

        $this->assertSame(2, AiUsage::query()->count());
    }

    public function test_the_limits_come_from_configuration()
    {
        config(['guesvia.ai.limits.per_employee_daily_turns' => 60]);
        config(['guesvia.ai.limits.per_hotel_daily_turns' => 2000]);

        $this->assertSame(60, UsageMeter::perEmployeeDailyTurns());
        $this->assertSame(2000, UsageMeter::perHotelDailyTurns());
    }

    private function employee(?Hotel $hotel = null): User
    {
        return User::factory()->employee()->create([
            'hotel_id' => ($hotel ?? Hotel::factory()->create())->id,
        ]);
    }
}

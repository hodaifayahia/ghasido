<?php

namespace Tests\Feature\Security;

use App\Contracts\AiUsageInfo;
use App\Enums\AiFeature;
use App\Models\Hotel;
use App\Models\HotelAiPointTopUp;
use App\Models\RoleplayAttempt;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Ai\AiLimitReached;
use App\Services\Ai\UsageMeter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

/**
 * Security audit 2026-11-08 (ai-pool-topup-perpetual-allocation): an
 * employee's allocation is a standing number, but the hotel pool behind it
 * is monthly. At spend time the hotel's points used this month must stay
 * within this month's pool (plan + this month's top-ups), so a top-up
 * allocated last month cannot fund every later month (AIL-01, SUB-01).
 */
class AiSpendHotelPoolTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_spend_is_refused_once_the_hotels_monthly_pool_is_used_up()
    {
        [$hotel, $first, $second] = $this->hotelWithPool(100);
        $meter = new UsageMeter;

        // The first employee spends the whole monthly pool (2 × 50).
        $meter->record($first, AiFeature::WritingEval, AiUsageInfo::none());
        $meter->record($first, AiFeature::WritingEval, AiUsageInfo::none());

        // The second still holds 2000 allocated points, but the pool is gone.
        $this->assertSame(2000, $meter->availablePoints($second));
        $this->assertSame(0, $meter->hotelPoolRemaining($second));

        try {
            $meter->assertWithinLimits($second, AiFeature::RoleplayTurn);
            $this->fail('An employee spent beyond the hotel\'s monthly pool.');
        } catch (AiLimitReached $exception) {
            $this->assertStringContainsString('0 AI points remaining', $exception->getMessage());
        }

        $this->assertNotNull($meter->practiceEvaluationRefusal($second));
    }

    public function test_last_months_top_up_does_not_fund_this_month()
    {
        Date::setTestNow(Date::parse('2026-11-15 12:00:00'));
        [$hotel, $first, $second] = $this->hotelWithPool(100);

        HotelAiPointTopUp::query()->create([
            'hotel_id' => $hotel->id,
            'month_start' => '2026-10-01',
            'points' => 5000,
            'currency' => 'DZD',
            'amount_dzd' => 1000,
            'received_at' => Date::parse('2026-10-05'),
            'kind' => 'paid',
        ]);

        $meter = new UsageMeter;
        $meter->record($first, AiFeature::WritingEval, AiUsageInfo::none());
        $meter->record($first, AiFeature::WritingEval, AiUsageInfo::none());

        $this->assertFalse($meter->isWithinLimits($second, AiFeature::RoleplayTurn));
    }

    public function test_charged_voice_calls_count_against_the_pool_and_stop_a_live_call()
    {
        [$hotel, $first, $second] = $this->hotelWithPool(300);
        RoleplayAttempt::factory()->create([
            'user_id' => $first->id,
            'started_at' => now(),
            'ai_points_charged' => 250,
        ]);
        $live = RoleplayAttempt::factory()->create([
            'user_id' => $second->id,
            'started_at' => now(),
        ]);

        $meter = new UsageMeter;

        $this->assertSame(50, $meter->hotelPoolRemaining($second));
        $this->assertFalse($meter->canContinueVoice($live));
    }

    /**
     * @return array{0: Hotel, 1: User, 2: User}
     */
    private function hotelWithPool(int $pool): array
    {
        $hotel = Hotel::factory()->create();
        SubscriptionPlan::query()->whereKey($hotel->subscription_plan_id)->update([
            'points_pool_override' => $pool,
            'ai_action_points' => 50,
            'voice_points_per_10_minutes' => 100,
        ]);

        $first = User::factory()->employee()->create(['hotel_id' => $hotel->id]);
        $second = User::factory()->employee()->create(['hotel_id' => $hotel->id]);

        return [$hotel, $first, $second];
    }
}

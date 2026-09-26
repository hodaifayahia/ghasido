<?php

namespace Tests\Feature\Admin;

use App\Enums\AiFeature;
use App\Models\IndividualSubscription;
use App\Models\User;
use App\Services\Ai\AiLimitReached;
use App\Services\Ai\UsageMeter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** An individual subscriber's own AI settings are what limit them (user request 2026-09-25). */
class IndividualAiLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_individual_without_ai_in_their_plan_is_refused(): void
    {
        $user = User::factory()->employee()->create(['hotel_id' => null, 'ai_points_allocated' => 3000]);
        IndividualSubscription::factory()->for($user)->withoutAi()->create();

        $this->expectException(AiLimitReached::class);

        app(UsageMeter::class)->assertWithinLimits($user->refresh(), AiFeature::RoleplayTurn);
    }

    public function test_an_individual_pays_their_own_action_cost_and_is_limited_by_their_points(): void
    {
        $user = User::factory()->employee()->create(['hotel_id' => null, 'ai_points_allocated' => 30]);
        IndividualSubscription::factory()->for($user)->create(['ai_action_points' => 40]);
        $meter = app(UsageMeter::class);

        $this->assertSame(40, $meter->aiActionCost($user->refresh()));
        $this->assertFalse($meter->isWithinLimits($user, AiFeature::RoleplayTurn));
    }
}

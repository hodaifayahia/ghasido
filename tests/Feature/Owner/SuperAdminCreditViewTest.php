<?php

namespace Tests\Feature\Owner;

use App\Enums\ApiAccount;
use App\Models\AiUsage;
use App\Models\ApiAccountSetting;
use App\Models\ApiCreditTopup;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Super Admin sees the AI credit the platform owner gave her, on her
 * dashboard and on Settings → AI usage (spec 0007, D11): dollars left of
 * her pack and the units left, never the owner's cost. Nobody else sees it.
 */
class SuperAdminCreditViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        ApiAccountSetting::for(ApiAccount::Qwen)->forceFill(['metering_started_at' => Date::now()->subMinute()])->save();
        ApiCreditTopup::query()->create(['account' => ApiAccount::Qwen, 'amount_usd' => 200, 'amount_tokens' => 1000]);
        AiUsage::factory()->create(['provider' => 'qwen', 'model' => 'qwen3.8-flash', 'prompt_tokens' => 250, 'completion_tokens' => 0, 'cost_estimate' => 0.07]);
    }

    public function test_her_dashboard_shows_the_dollars_left_of_her_pack()
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('aiCredit.0.account', 'qwen')
                ->where('aiCredit.0.service', 'AI text & images')
                ->where('aiCredit.0.creditUsd', 200)
                ->where('aiCredit.0.remainingUsd', 150)
                ->where('aiCredit.0.meters.0.left', 750)
                ->missing('aiCredit.0.costUsd')
                ->where('aiCredit.1.state', 'unlimited')
                ->where('aiCredit.1.usedUsd', null));
    }

    public function test_the_ai_usage_page_shows_it_too()
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('ai-usage.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/AiUsage')
                ->where('aiCredit.0.remainingUsd', 150));
    }

    public function test_hotel_admins_and_managers_never_receive_it()
    {
        $hotel = Hotel::factory()->create();

        foreach ([
            User::factory()->admin()->create(['hotel_id' => $hotel->id]),
            User::factory()->manager()->create(['hotel_id' => $hotel->id]),
        ] as $user) {
            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertInertia(fn (Assert $page) => $page->missing('aiCredit'));
        }
    }
}

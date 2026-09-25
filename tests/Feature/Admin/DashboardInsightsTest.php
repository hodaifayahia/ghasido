<?php

namespace Tests\Feature\Admin;

use App\Contracts\AiProvider;
use App\Enums\AccountStatus;
use App\Enums\AiFeature;
use App\Enums\GenerationStatus;
use App\Jobs\GenerateDashboardBriefing;
use App\Models\AiInsight;
use App\Models\AiUsage;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\RoleplayAttempt;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Ai\FakeAiProvider;
use App\Services\Dashboard\DashboardBriefing;
use App\Services\Dashboard\DashboardStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * At-risk learners and the AI briefing on the Dashboard (spec 0005 §4.1):
 * rule-based risk, hotel-scoped, and a briefing written by a job from
 * aggregate figures that never name a learner.
 */
class DashboardInsightsTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private Department $reception;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Carbon::setTestNow('2026-09-24 12:00:00');
        config(['guesvia.reminders.inactive_days' => 5]);

        $this->hotel = Hotel::factory()->create();
        $this->reception = Department::factory()->create(['name' => 'Reception']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employee(array $attributes = [], ?Hotel $hotel = null): User
    {
        return User::factory()->employee()->create($attributes + [
            'hotel_id' => ($hotel ?? $this->hotel)->id,
            'department_id' => $this->reception->id,
            'status' => AccountStatus::Active,
            'created_at' => now()->subDays(30),
        ]);
    }

    public function test_risk_signals_add_up_with_their_reasons()
    {
        $test = Test::factory()->pre()->create(['department_id' => $this->reception->id]);

        // Idle for 10 days (2) + slow progress (1) + a low Pre-test (1) + low
        // role-play (1) = high.
        $struggling = $this->employee(['name' => 'Struggling Sam', 'training_started_at' => now()->subDays(20), 'last_activity_at' => now()->subDays(10)]);
        TestAttempt::factory()->submitted(5, 25)->create(['user_id' => $struggling->id, 'test_id' => $test->id, 'submitted_at' => now()->subDays(19)]);
        RoleplayAttempt::factory()->completed()->create(['user_id' => $struggling->id, 'overall_score' => 30]);

        // Never started, account 30 days old (2) = medium.
        $this->employee(['name' => 'Silent Sara']);

        // Active yesterday with a decent Pre-test: not listed.
        $fine = $this->employee(['name' => 'Fine Farid', 'training_started_at' => now()->subDays(3), 'last_activity_at' => now()->subDay()]);
        TestAttempt::factory()->submitted(20, 25)->create(['user_id' => $fine->id, 'test_id' => $test->id]);

        $atRisk = app(DashboardStats::class)->build($this->hotel)['atRisk'];

        $this->assertSame(2, $atRisk['total']);
        $this->assertSame(1, $atRisk['high']);
        $this->assertSame(1, $atRisk['medium']);
        $this->assertSame('Struggling Sam', $atRisk['rows'][0]['name']);
        $this->assertSame('high', $atRisk['rows'][0]['level']);
        $this->assertContains('No activity for 10 days', $atRisk['rows'][0]['reasons']);
        $this->assertContains('Low Pre-test score (20%)', $atRisk['rows'][0]['reasons']);
        $this->assertContains('Role-play average 30/100', $atRisk['rows'][0]['reasons']);
        $this->assertContains('Slow progress: 0% after 20 days', $atRisk['rows'][0]['reasons']);
        $this->assertSame(['inactive' => 1, 'lowPreTest' => 1, 'lowRoleplay' => 1, 'notStarted' => 1, 'slowProgress' => 1], $this->sorted($atRisk['reasons']));
    }

    public function test_a_learner_who_completed_training_is_never_at_risk()
    {
        $this->employee(['training_started_at' => now()->subDays(40), 'training_completed_at' => now()->subDays(20), 'last_activity_at' => now()->subDays(20)]);

        $this->assertSame(0, app(DashboardStats::class)->build($this->hotel)['atRisk']['total']);
    }

    public function test_a_managers_list_and_briefing_stay_in_their_hotel()
    {
        Queue::fake();
        $this->employee(['name' => 'Our Learner']);
        $this->employee(['name' => 'Their Learner'], Hotel::factory()->create());
        $manager = User::factory()->manager()->create(['hotel_id' => $this->hotel->id]);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('atRisk.total', 1)
                ->where('atRisk.rows.0.name', 'Our Learner')
                ->where('briefing.status', 'pending')
                ->etc());

        Queue::assertPushed(GenerateDashboardBriefing::class, fn (GenerateDashboardBriefing $job): bool => $job->hotelId === $this->hotel->id);
    }

    public function test_the_briefing_is_written_from_figures_without_names_and_billed_to_the_hotel()
    {
        $this->app->instance(AiProvider::class, new FakeAiProvider);
        $this->employee(['name' => 'Very Private Name']);
        $manager = User::factory()->manager()->create(['hotel_id' => $this->hotel->id]);

        $built = app(DashboardStats::class)->build($this->hotel);
        $this->assertStringNotContainsString('Very Private Name', (string) json_encode(DashboardBriefing::context($this->hotel, $built)));

        // Sync queue: the job runs inside the request.
        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('briefing.status', 'ready')
                ->has('briefing.actions', 2)
                ->etc());

        $usage = AiUsage::query()->where('feature', AiFeature::DashboardBriefing->value)->sole();
        $this->assertNull($usage->user_id);
        $this->assertSame($this->hotel->id, $usage->hotel_id);
        $this->assertSame(0, (int) $usage->points_charged);
    }

    public function test_the_super_admin_reads_the_portfolio_briefing()
    {
        Queue::fake();
        $this->employee();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('briefing.status', 'pending')->etc());

        Queue::assertPushed(GenerateDashboardBriefing::class, fn (GenerateDashboardBriefing $job): bool => $job->hotelId === null);
        $this->assertSame(GenerationStatus::Pending, AiInsight::forSubject(AiInsight::SUBJECT_PORTFOLIO, 0, AiInsight::KIND_HOTEL_BRIEFING)?->status);
    }

    public function test_no_briefing_is_queued_for_an_empty_hotel()
    {
        Queue::fake();
        $manager = User::factory()->manager()->create(['hotel_id' => $this->hotel->id]);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('briefing.status', 'empty')->where('atRisk.total', 0)->etc());

        Queue::assertNotPushed(GenerateDashboardBriefing::class);
    }

    /**
     * @param  array<string, int>  $tally
     * @return array<string, int>
     */
    private function sorted(array $tally): array
    {
        ksort($tally);

        return $tally;
    }
}

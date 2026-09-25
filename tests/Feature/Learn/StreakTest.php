<?php

namespace Tests\Feature\Learn;

use App\Models\BlockCompletion;
use App\Models\PhrasebookItem;
use App\Models\RoleplayAttempt;
use App\Models\User;
use App\Services\Learning\StreakService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The practice streak (spec 0005 §3.2): days in a row with some learning,
 * read from the rows the learner already writes.
 */
class StreakTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
        $this->travelTo(now()->setDate(2026, 9, 24)->setTime(15, 0));
    }

    private function stepOn(User $learner, int $daysAgo): void
    {
        BlockCompletion::factory()->create([
            'user_id' => $learner->id,
            'completed_at' => now()->subDays($daysAgo),
        ]);
    }

    public function test_consecutive_days_ending_today_make_the_streak()
    {
        $learner = $this->learner();
        $this->stepOn($learner, 0);
        $this->stepOn($learner, 1);
        $this->stepOn($learner, 2);
        $this->stepOn($learner, 4); // a gap breaks the run

        $streak = app(StreakService::class)->summary($learner);

        $this->assertSame(3, $streak['current']);
        $this->assertTrue($streak['activeToday']);
        $this->assertSame(3, $streak['best']);
        $this->assertCount(7, $streak['week']);
        $this->assertTrue($streak['week'][6]['today']);
        $this->assertTrue($streak['week'][6]['active']);
        $this->assertFalse($streak['week'][3]['active']);
    }

    public function test_yesterdays_streak_survives_until_the_learner_practises_today()
    {
        $learner = $this->learner();
        $this->stepOn($learner, 1);
        $this->stepOn($learner, 2);

        $streak = app(StreakService::class)->summary($learner);

        $this->assertSame(2, $streak['current']);
        $this->assertFalse($streak['activeToday']);
    }

    public function test_the_best_run_is_kept_after_a_break()
    {
        $learner = $this->learner();
        foreach ([10, 11, 12, 13] as $daysAgo) {
            $this->stepOn($learner, $daysAgo);
        }

        $streak = app(StreakService::class)->summary($learner);

        $this->assertSame(0, $streak['current']);
        $this->assertSame(4, $streak['best']);
    }

    public function test_every_kind_of_practice_counts_but_an_admin_preview_does_not()
    {
        $learner = $this->learner();
        PhrasebookItem::factory()->create(['user_id' => $learner->id, 'last_reviewed_at' => now()]);
        RoleplayAttempt::factory()->create(['user_id' => $learner->id, 'started_at' => now()->subDay()]);
        RoleplayAttempt::factory()->create(['user_id' => $learner->id, 'started_at' => now()->subDays(2), 'is_preview' => true]);

        $streak = app(StreakService::class)->summary($learner);

        $this->assertSame(2, $streak['current']);
    }

    public function test_home_and_progress_carry_the_streak()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $this->stepOn($learner, 0);

        $this->actingAs($learner)->get(route('learn.home'))
            ->assertInertia(fn (Assert $page) => $page->where('streak.current', 1)->etc());

        $this->actingAs($learner)->get(route('learn.progress'))
            ->assertInertia(fn (Assert $page) => $page->where('streak.activeToday', true)->etc());
    }
}

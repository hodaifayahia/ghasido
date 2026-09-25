<?php

namespace Tests\Feature\Admin\Reports;

use App\Enums\ActivityType;
use App\Enums\GenerationStatus;
use App\Enums\Permission;
use App\Enums\RoleplayStatus;
use App\Jobs\EvaluateRoleplayAttempt;
use App\Jobs\EvaluateWrittenAnswer;
use App\Models\Activity;
use App\Models\Attempt;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Reports\ScoreOverrides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * An admin replaces, restores or re-grades an AI score (AIE-05, SEC-06,
 * ROLE-02; spec 0005 §2.5).
 */
class ScoreOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    public function test_an_override_keeps_the_ai_score_moves_the_sitting_and_is_audited()
    {
        $world = ReportsWorld::build();
        $answer = $world->aliceAnswer;
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->patch(route('reports.answers.score.update', $answer), ['score' => 0, 'reason' => 'Accepted the wrong option by mistake.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $answer->refresh();
        $this->assertSame('0.00', $answer->score);
        $this->assertSame('1.00', $answer->original_score);
        $this->assertSame($admin->id, $answer->score_overridden_by);
        $this->assertSame('Accepted the wrong option by mistake.', $answer->score_override_reason);
        $this->assertNotNull($answer->score_overridden_at);

        // The auto-graded answer counts toward its sitting: 15 → 14.
        $this->assertSame('14.00', (string) $world->alicePre->fresh()?->score);

        $audit = AuditLog::query()->where('action', ScoreOverrides::AUDIT_OVERRIDE)->sole();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame('Accepted the wrong option by mistake.', $audit->changes['reason'] ?? null);

        // A second override keeps the machine's value, not the first override.
        $this->actingAs($admin)->patch(route('reports.answers.score.update', $answer), ['score' => 0.5, 'reason' => 'Half credit after review.']);
        $this->assertSame('1.00', $answer->fresh()?->original_score);
        $this->assertSame('14.50', (string) $world->alicePre->fresh()?->score);
    }

    public function test_restoring_puts_the_ai_score_and_the_sitting_total_back()
    {
        $world = ReportsWorld::build();
        $answer = $world->aliceAnswer;
        $admin = $this->superAdmin();

        $this->actingAs($admin)->patch(route('reports.answers.score.update', $answer), ['score' => 0, 'reason' => 'Review']);
        $this->actingAs($admin)->delete(route('reports.answers.score.restore', $answer))->assertRedirect();

        $answer->refresh();
        $this->assertSame('1.00', $answer->score);
        $this->assertNull($answer->original_score);
        $this->assertFalse($answer->isScoreOverridden());
        $this->assertSame('15.00', (string) $world->alicePre->fresh()?->score);
        $this->assertDatabaseHas('audit_logs', ['action' => ScoreOverrides::AUDIT_RESTORE]);
    }

    public function test_the_score_is_bounded_and_the_reason_is_required()
    {
        $world = ReportsWorld::build();

        $this->actingAs($this->superAdmin())
            ->patch(route('reports.answers.score.update', $world->aliceAnswer), ['score' => 5, 'reason' => ''])
            ->assertSessionHasErrors(['score', 'reason']);

        $this->assertFalse($world->aliceAnswer->fresh()?->isScoreOverridden());
    }

    public function test_a_hotel_admin_or_manager_cannot_override()
    {
        $world = ReportsWorld::build();
        $hotelAdmin = User::factory()->admin()->create(['hotel_id' => $world->hotelA->id]);

        foreach ([$hotelAdmin, $world->managerOfA()] as $user) {
            $this->actingAs($user)
                ->patch(route('reports.answers.score.update', $world->aliceAnswer), ['score' => 0, 'reason' => 'No'])
                ->assertForbidden();
            $this->actingAs($user)
                ->post(route('reports.roleplay.reevaluate', $world->aliceRoleplay))
                ->assertForbidden();
        }

        $this->assertFalse($world->aliceAnswer->fresh()?->isScoreOverridden());
    }

    public function test_a_granted_hotel_user_stays_inside_their_own_hotel()
    {
        // The permission can be given to a custom role; the policy still
        // keeps it inside the actor's hotel (ROLE-02).
        $world = ReportsWorld::build();
        $reviewer = User::factory()->admin()->create(['hotel_id' => $world->hotelB->id]);
        $reviewer->givePermissionTo(Permission::ScoresOverride->value);

        $this->actingAs($reviewer)
            ->patch(route('reports.answers.score.update', $world->aliceAnswer), ['score' => 0, 'reason' => 'Cross hotel'])
            ->assertForbidden();
    }

    public function test_the_page_offers_adjusting_only_to_holders_of_the_permission()
    {
        $world = ReportsWorld::build();

        $this->actingAs($this->superAdmin())
            ->get(route('reports-export', ['tab' => 'detailedAnswers']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canOverrideScores', true)
                ->where('results.rows.0.overridden', false)
                ->where('results.rows.0.aiGraded', false)
                ->etc());

        $this->actingAs(User::factory()->admin()->create(['hotel_id' => $world->hotelA->id]))
            ->get(route('reports-export'))
            ->assertInertia(fn (Assert $page) => $page->where('canOverrideScores', false)->etc());
    }

    public function test_a_written_answer_can_be_graded_again_and_an_auto_graded_one_cannot()
    {
        Queue::fake();
        $world = ReportsWorld::build();
        $admin = $this->superAdmin();
        $written = Attempt::factory()->inTest($world->alicePre)->create([
            'user_id' => $world->alice->id,
            'activity_id' => Activity::factory()->ofType(ActivityType::Writing)->create()->id,
            'raw_answer' => ['i1' => ['text' => 'Dear guest, your room is ready.']],
            'score' => 60,
            'max_score' => 100,
            'ai_status' => GenerationStatus::Done,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('reports.answers.reevaluate', $written))->assertRedirect();

        $this->assertSame(GenerationStatus::Pending, $written->fresh()?->ai_status);
        Queue::assertPushed(EvaluateWrittenAnswer::class, fn (EvaluateWrittenAnswer $job): bool => $job->attemptId === $written->id);

        // A multiple-choice answer has no AI verdict to redo.
        $this->actingAs($admin)->post(route('reports.answers.reevaluate', $world->aliceAnswer));
        Queue::assertPushed(EvaluateWrittenAnswer::class, 1);
    }

    public function test_a_role_play_override_survives_a_re_grade()
    {
        $world = ReportsWorld::build();
        $admin = $this->superAdmin();
        $roleplay = $world->aliceRoleplay;

        $this->actingAs($admin)
            ->patch(route('reports.roleplay.score.update', $roleplay), ['score' => 95, 'reason' => 'Handled the complaint very well.'])
            ->assertSessionHasNoErrors();

        $roleplay->refresh();
        $this->assertSame(95, $roleplay->overall_score);
        $this->assertTrue($roleplay->isScoreOverridden());

        // The job runs inline (sync queue) with the fake provider (70).
        $this->actingAs($admin)->post(route('reports.roleplay.reevaluate', $roleplay))->assertRedirect();

        $roleplay->refresh();
        $this->assertSame(RoleplayStatus::Completed, $roleplay->status);
        $this->assertSame(95, $roleplay->overall_score);
        $this->assertSame(70, $roleplay->original_overall_score);

        $this->actingAs($admin)->delete(route('reports.roleplay.score.restore', $roleplay));
        $this->assertSame(70, $roleplay->fresh()?->overall_score);
    }

    public function test_an_open_conversation_cannot_be_re_graded()
    {
        Queue::fake();
        $world = ReportsWorld::build();
        $world->aliceRoleplay->forceFill(['status' => RoleplayStatus::InProgress])->save();

        $this->actingAs($this->superAdmin())->post(route('reports.roleplay.reevaluate', $world->aliceRoleplay));

        Queue::assertNotPushed(EvaluateRoleplayAttempt::class);
        $this->assertSame(RoleplayStatus::InProgress, $world->aliceRoleplay->fresh()?->status);
    }
}

<?php

namespace Tests\Feature\Learn;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The first-login gate (AUTH-04, AUTH-05, AUTH-06, PRIV-01, PRIV-02, REM-05;
 * spec 0003 Part E).
 */
class FirstLoginGateTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    public function test_an_employee_who_has_not_completed_first_login_is_redirected_from_every_learner_route()
    {
        $learner = $this->learner(['first_login_completed_at' => null, 'research_notice_acknowledged_at' => null]);

        foreach (['learn.home', 'learn.lessons', 'learn.phrasebook', 'learn.progress', 'learn.messages', 'learn.certificate'] as $name) {
            $this->actingAs($learner)
                ->get(route($name))
                ->assertRedirect(route('learn.first-login.edit'));
        }
    }

    public function test_the_first_login_screen_itself_is_reachable()
    {
        $learner = $this->learner(['first_login_completed_at' => null, 'email' => null]);

        $this->actingAs($learner)
            ->get(route('learn.first-login.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employee/FirstLogin')
                ->where('user.email', null)
                ->where('user.reminderConsent', false)
                ->where('requireEmail', false)
                ->where('hotelName', $this->hotel->name)
                ->where('departmentName', $this->department->name)
            );
    }

    public function test_completing_first_login_sets_the_timestamps_and_opens_the_home()
    {
        $learner = $this->learner(['first_login_completed_at' => null, 'research_notice_acknowledged_at' => null, 'email' => null]);

        $response = $this->actingAs($learner)->post(route('learn.first-login.update'), [
            'email' => 'Samira@Example.test',
            'reminder_consent' => '1',
            'research_notice_acknowledged' => '1',
        ]);

        $response->assertRedirect(route('learn.home'));

        $learner->refresh();

        $this->assertSame('samira@example.test', $learner->email);
        $this->assertNotNull($learner->first_login_completed_at);
        $this->assertNotNull($learner->research_notice_acknowledged_at);
        $this->assertNotNull($learner->email_consent_at);
        $this->assertTrue($learner->consentsToReminders());

        $this->assertDatabaseHas('audit_logs', ['actor_id' => $learner->id, 'action' => 'first_login.completed']);

        $this->actingAs($learner)->get(route('learn.home'))->assertOk();
    }

    public function test_declining_consent_records_no_consent_and_the_gate_still_opens()
    {
        $learner = $this->learner(['first_login_completed_at' => null, 'email_consent_at' => now()]);

        $this->actingAs($learner)->post(route('learn.first-login.update'), [
            'email' => 'samira@example.test',
            'reminder_consent' => '0',
            'research_notice_acknowledged' => '1',
        ])->assertRedirect(route('learn.home'));

        $learner->refresh();

        $this->assertNull($learner->email_consent_at);
        $this->assertNotNull($learner->first_login_completed_at);
    }

    public function test_the_research_notice_must_be_acknowledged()
    {
        $learner = $this->learner(['first_login_completed_at' => null]);

        $this->actingAs($learner)
            ->from(route('learn.first-login.edit'))
            ->post(route('learn.first-login.update'), [
                'email' => 'samira@example.test',
                'reminder_consent' => '1',
            ])
            ->assertSessionHasErrors('research_notice_acknowledged');

        $this->assertNull($learner->fresh()?->first_login_completed_at);
    }

    public function test_email_is_optional_unless_the_hotel_requires_it()
    {
        $learner = $this->learner(['first_login_completed_at' => null, 'email' => null]);

        $this->actingAs($learner)->post(route('learn.first-login.update'), [
            'research_notice_acknowledged' => '1',
        ])->assertRedirect(route('learn.home'));

        $this->assertNull($learner->fresh()?->email);
        $this->assertNotNull($learner->fresh()?->first_login_completed_at);
    }

    public function test_email_is_required_when_the_hotel_says_so()
    {
        $this->hotel->forceFill(['settings' => ['require_email' => true]])->save();
        $learner = $this->learner(['first_login_completed_at' => null, 'email' => null]);

        $this->actingAs($learner)
            ->get(route('learn.first-login.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('requireEmail', true));

        $this->actingAs($learner)
            ->from(route('learn.first-login.edit'))
            ->post(route('learn.first-login.update'), [
                'research_notice_acknowledged' => '1',
            ])
            ->assertSessionHasErrors('email');

        $this->assertNull($learner->fresh()?->first_login_completed_at);
    }

    public function test_an_employee_past_first_login_is_not_bounced()
    {
        $this->actingAs($this->learner())
            ->get(route('learn.home'))
            ->assertOk();
    }
}

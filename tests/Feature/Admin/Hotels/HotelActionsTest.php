<?php

namespace Tests\Feature\Admin\Hotels;

use App\Enums\AccountStatus;
use App\Enums\CapacityState;
use App\Enums\HotelAccessState;
use App\Enums\HotelStatus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\SeatQuota;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Hotels\SeatQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Every write on a hotel: create, edit, approve, reject, archive, extend,
 * pause, resume and seat quotas, each with exactly one audit row
 * (spec 0002, AC-5, AC-7, AC-12 to AC-16).
 */
class HotelActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->owner = User::factory()->superAdmin()->create();
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    // -------------------------------------------------------------- create

    public function test_creating_a_hotel_lands_pending_with_an_audit_row()
    {
        $this->actingAs($this->owner)
            ->from(route('hotels'))
            ->post(route('hotels.store'), $this->validHotel())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $hotel = Hotel::query()->where('name', 'Palm Court Hotel')->firstOrFail();

        $this->assertSame(HotelAccessState::Pending, $hotel->access_state);
        $this->assertSame('palm-court-hotel', $hotel->slug);
        $this->assertSame('2026-10-01', $hotel->contract_starts_on?->toDateString());
        $this->assertSame('2026-11-30', $hotel->contract_ends_on?->toDateString());
        $this->assertOneAuditRow($hotel, 'hotel.created');
    }

    public function test_the_state_is_never_taken_from_the_form()
    {
        $this->actingAs($this->owner)
            ->post(route('hotels.store'), $this->validHotel(['access_state' => 'active']));

        $this->assertSame(HotelAccessState::Pending, Hotel::query()->firstOrFail()->access_state);
    }

    public function test_a_duplicate_name_gets_a_counted_slug()
    {
        Hotel::factory()->create(['name' => 'Palm Court Hotel', 'slug' => 'palm-court-hotel']);

        $this->actingAs($this->owner)->post(route('hotels.store'), $this->validHotel());

        $this->assertTrue(Hotel::query()->where('slug', 'palm-court-hotel-2')->exists());
    }

    public function test_bad_input_is_refused_with_errors_beside_the_fields()
    {
        $this->actingAs($this->owner)
            ->from(route('hotels'))
            ->post(route('hotels.store'), [
                'name' => '',
                'city' => str_repeat('x', 121),
                'manager_name' => 'Ok',
                'manager_email' => 'not-an-email',
                'contract_starts_on' => '2026-10-10',
                'contract_ends_on' => '2026-10-01',
            ])
            ->assertRedirect(route('hotels'))
            ->assertSessionHasErrors(['name', 'city', 'manager_email', 'contract_ends_on']);

        $this->assertSame(0, Hotel::count());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_a_manager_email_may_run_two_hotels()
    {
        Hotel::factory()->create(['manager_email' => 'same@example.com']);

        $this->actingAs($this->owner)
            ->post(route('hotels.store'), $this->validHotel(['manager_email' => 'same@example.com']))
            ->assertSessionHasNoErrors();
    }

    // ---------------------------------------------------------------- edit

    public function test_editing_writes_the_diff_to_the_audit_row()
    {
        $hotel = Hotel::factory()->create(['city' => 'Algiers']);

        $this->actingAs($this->owner)
            ->patch(route('hotels.update', $hotel), $this->validHotel(['city' => 'Oran']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $log = $this->assertOneAuditRow($hotel, 'hotel.updated');
        $this->assertSame(['from' => 'Algiers', 'to' => 'Oran'], $log->changes['attributes']['city'] ?? null);
        $this->assertSame($this->owner->id, $log->actor_id);
    }

    // ------------------------------------------------------------- approve

    public function test_approving_late_preserves_the_duration_while_moving_both_dates()
    {
        $hotel = Hotel::factory()->pending()->create([
            'contract_starts_on' => '2026-09-01',
            'contract_ends_on' => '2026-10-31',
        ]);
        $duration = $hotel->contract_starts_on?->diffInDays($hotel->contract_ends_on);

        Date::setTestNow(Date::parse('2026-09-15 10:00:00'));

        $this->actingAs($this->owner)
            ->post(route('hotels.approve', $hotel))
            ->assertRedirect();

        $hotel->refresh();

        $this->assertSame(HotelAccessState::Active, $hotel->access_state);
        $this->assertSame('2026-09-15', $hotel->contract_starts_on?->toDateString());
        $this->assertSame('2026-11-14', $hotel->contract_ends_on?->toDateString());
        $this->assertSame($duration, $hotel->contract_starts_on?->diffInDays($hotel->contract_ends_on));
        $this->assertSame($this->owner->id, $hotel->approved_by);
        $this->assertNotNull($hotel->approved_at);
        $this->assertOneAuditRow($hotel, 'hotel.approved');
    }

    public function test_approving_a_hotel_that_is_not_pending_is_a_conflict()
    {
        $hotel = Hotel::factory()->active()->create();

        $this->actingAs($this->owner)
            ->post(route('hotels.approve', $hotel))
            ->assertStatus(409);

        $this->assertSame(0, AuditLog::count());
    }

    // -------------------------------------------------------------- reject

    public function test_rejecting_archives_with_a_reason_and_adds_no_new_state()
    {
        $hotel = Hotel::factory()->pending()->create();

        $this->actingAs($this->owner)
            ->post(route('hotels.reject', $hotel), ['reason' => 'Duplicate signup'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $hotel->refresh();

        $this->assertSame(HotelAccessState::Archived, $hotel->access_state);
        $this->assertSame('Duplicate signup', $hotel->archive_reason);
        $this->assertNotNull($hotel->archived_at);
        $this->assertContains($hotel->access_state->value, array_column(HotelAccessState::cases(), 'value'));

        $log = $this->assertOneAuditRow($hotel, 'hotel.rejected');
        $this->assertSame('Duplicate signup', $log->changes['reason'] ?? null);
    }

    public function test_rejecting_requires_a_reason()
    {
        $hotel = Hotel::factory()->pending()->create();

        $this->actingAs($this->owner)
            ->from(route('hotels'))
            ->post(route('hotels.reject', $hotel), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(HotelAccessState::Pending, $hotel->fresh()?->access_state);
    }

    // ------------------------------------------------------------- archive

    public function test_archiving_keeps_every_row_and_drops_out_of_the_default_view()
    {
        $department = Department::factory()->create();
        $hotel = Hotel::factory()->create();
        SeatQuota::factory()->create(['hotel_id' => $hotel->id, 'department_id' => $department->id]);
        User::factory()->employee()->count(3)->forHotel($hotel, $department)->create();

        $this->actingAs($this->owner)
            ->post(route('hotels.archive', $hotel), ['reason' => 'Contract not renewed'])
            ->assertRedirect();

        $this->assertSame(HotelAccessState::Archived, $hotel->fresh()?->access_state);
        $this->assertSame(3, User::where('hotel_id', $hotel->id)->where('status', AccountStatus::Active)->count());
        $this->assertSame(1, SeatQuota::withoutGlobalScopes()->where('hotel_id', $hotel->id)->count());
        $this->assertSame(0, Hotel::totalInPortfolio());
        $this->assertOneAuditRow($hotel, 'hotel.archived');

        $this->actingAs($this->owner)
            ->post(route('hotels.archive', $hotel))
            ->assertStatus(409);
    }

    // -------------------------------------------------------------- extend

    public function test_extending_an_ended_contract_brings_it_back_with_no_state_change()
    {
        $hotel = Hotel::factory()->ended(5)->create();
        $this->assertSame(HotelStatus::Ended, $hotel->derivedStatus());

        $newEnd = Date::today()->addDays(90)->toDateString();

        $this->actingAs($this->owner)
            ->patch(route('hotels.contract', $hotel), ['contract_ends_on' => $newEnd])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $hotel->refresh();

        $this->assertSame(HotelStatus::Active, $hotel->derivedStatus());
        $this->assertSame(HotelAccessState::Active, $hotel->access_state);
        $this->assertSame($newEnd, $hotel->contract_ends_on?->toDateString());

        $log = $this->assertOneAuditRow($hotel, 'hotel.contract_extended');
        $this->assertArrayHasKey('contract_ends_on', $log->changes['attributes'] ?? []);
    }

    public function test_an_end_date_before_the_start_is_refused()
    {
        $hotel = Hotel::factory()->create(['contract_starts_on' => '2026-09-01']);

        $this->actingAs($this->owner)
            ->from(route('hotels'))
            ->patch(route('hotels.contract', $hotel), ['contract_ends_on' => '2026-08-01'])
            ->assertSessionHasErrors('contract_ends_on');
    }

    // ------------------------------------------------------- pause / resume

    public function test_days_remaining_is_invariant_across_a_pause_and_a_resume()
    {
        Date::setTestNow(Date::parse('2026-09-18 09:00:00'));
        $hotel = Hotel::factory()->active(45)->create();
        $before = $hotel->daysRemaining();
        $this->assertSame(45, $before);

        $this->actingAs($this->owner)->post(route('hotels.pause', $hotel))->assertRedirect();

        $hotel->refresh();
        $this->assertSame(HotelAccessState::Paused, $hotel->access_state);
        $this->assertNotNull($hotel->paused_at);
        $this->assertNull($hotel->daysRemaining());
        $this->assertSame(HotelStatus::Paused, $hotel->derivedStatus());

        // Nine days and a few hours later, resume.
        Date::setTestNow(Date::parse('2026-09-27 17:30:00'));

        $this->actingAs($this->owner)->post(route('hotels.resume', $hotel))->assertRedirect();

        $hotel->refresh();
        $this->assertSame(HotelAccessState::Active, $hotel->access_state);
        $this->assertNull($hotel->paused_at);
        $this->assertSame($before, $hotel->daysRemaining());

        $this->assertSame(2, AuditLog::where('auditable_id', $hotel->id)->count());
        $this->assertSame(
            ['hotel.paused', 'hotel.resumed'],
            AuditLog::where('auditable_id', $hotel->id)->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_a_pause_shorter_than_a_calendar_day_adds_no_days()
    {
        Date::setTestNow(Date::parse('2026-09-18 09:00:00'));
        $hotel = Hotel::factory()->active(30)->create();

        $this->actingAs($this->owner)->post(route('hotels.pause', $hotel));

        Date::setTestNow(Date::parse('2026-09-18 21:00:00'));
        $this->actingAs($this->owner)->post(route('hotels.resume', $hotel));

        $this->assertSame(30, $hotel->fresh()?->daysRemaining());
    }

    public function test_pause_and_resume_refuse_the_wrong_state()
    {
        $paused = Hotel::factory()->paused()->create();
        $active = Hotel::factory()->active()->create();

        $this->actingAs($this->owner)->post(route('hotels.pause', $paused))->assertStatus(409);
        $this->actingAs($this->owner)->post(route('hotels.resume', $active))->assertStatus(409);

        $this->assertSame(0, AuditLog::count());
    }

    // --------------------------------------------------------- seat quotas

    public function test_a_quota_save_writes_one_audit_row_listing_only_what_changed()
    {
        $hotel = Hotel::factory()->create();
        [$reception, $spa, $kitchen] = Department::factory()->count(3)->create();
        SeatQuota::factory()->create(['hotel_id' => $hotel->id, 'department_id' => $reception->id, 'allowed_seats' => 8]);
        SeatQuota::factory()->create(['hotel_id' => $hotel->id, 'department_id' => $spa->id, 'allowed_seats' => 6]);

        $this->actingAs($this->owner)
            ->put(route('hotels.seat-quotas', $hotel), [
                'quotas' => [
                    ['department_id' => $reception->id, 'allowed_seats' => 10],
                    ['department_id' => $spa->id, 'allowed_seats' => 6],
                    ['department_id' => $kitchen->id, 'allowed_seats' => 4],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $log = $this->assertOneAuditRow($hotel, 'hotel.seats_updated');
        $this->assertSame([
            $reception->id => ['from' => 8, 'to' => 10],
            $kitchen->id => ['from' => 0, 'to' => 4],
        ], $log->changes['quotas'] ?? null);

        $this->assertSame(3, $hotel->departmentCount());
        $this->assertSame(20, $hotel->allowedSeats());
    }

    public function test_a_department_outside_the_hotels_catalogue_is_refused()
    {
        $hotel = Hotel::factory()->create();
        $foreign = Department::factory()->forHotel(Hotel::factory()->create()->id)->create();

        $this->actingAs($this->owner)
            ->from(route('hotels'))
            ->put(route('hotels.seat-quotas', $hotel), [
                'quotas' => [['department_id' => $foreign->id, 'allowed_seats' => 4]],
            ])
            ->assertSessionHasErrors('quotas.0.department_id');
    }

    public function test_lowering_a_quota_below_usage_changes_no_account_and_refuses_the_next_one()
    {
        $hotel = Hotel::factory()->create();
        $hotel->update(['subscription_plan_id' => SubscriptionPlan::query()->where('slug', 'diamond')->value('id')]);
        $department = Department::factory()->create(['name' => 'Reception']);
        SeatQuota::factory()->create(['hotel_id' => $hotel->id, 'department_id' => $department->id, 'allowed_seats' => 8]);
        User::factory()->employee()->count(6)->forHotel($hotel, $department)->create();

        $this->actingAs($this->owner)
            ->put(route('hotels.seat-quotas', $hotel), [
                'quotas' => [['department_id' => $department->id, 'allowed_seats' => 4]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(6, User::where('hotel_id', $hotel->id)->where('status', AccountStatus::Active)->count());
        $this->assertSame(CapacityState::Over, $hotel->fresh()?->capacityState());

        // The refusal at account creation time (SUB-02, SUB-03), service level.
        try {
            app(SeatQuotaService::class)->assertSeatAvailable($hotel, $department);
            $this->fail('Expected the next account to be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Reception', $e->errors()['department_id'][0]);
        }
    }

    public function test_a_department_with_a_free_seat_accepts_the_next_account()
    {
        $hotel = Hotel::factory()->create();
        $department = Department::factory()->create();
        SeatQuota::factory()->create(['hotel_id' => $hotel->id, 'department_id' => $department->id, 'allowed_seats' => 2]);
        User::factory()->employee()->forHotel($hotel, $department)->create();

        app(SeatQuotaService::class)->assertSeatAvailable($hotel, $department);

        $this->assertTrue(true);
    }

    public function test_a_department_with_no_quota_refuses_an_account()
    {
        $hotel = Hotel::factory()->create();
        $department = Department::factory()->create();

        $this->expectException(ValidationException::class);

        app(SeatQuotaService::class)->assertSeatAvailable($hotel, $department);
    }

    // ------------------------------------------------------------ login block

    public function test_a_blocked_hotel_names_its_reason_and_keeps_every_row()
    {
        $department = Department::factory()->create();

        $cases = [
            [Hotel::factory()->pending()->create(), 'waiting for approval'],
            [Hotel::factory()->paused()->create(), 'paused'],
            [Hotel::factory()->archived()->create(), 'no longer active'],
            [Hotel::factory()->ended()->create(), 'training period has ended'],
        ];

        foreach ($cases as [$hotel, $fragment]) {
            $employee = User::factory()->employee()->forHotel($hotel, $department)->create();

            $this->assertFalse($hotel->allowsAccess());
            $this->assertStringContainsString($fragment, $hotel->blockedMessage());
            $this->assertTrue(User::whereKey($employee->id)->exists());
        }

        $this->assertTrue(Hotel::factory()->active(45)->create()->allowsAccess());
    }

    // ------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validHotel(array $overrides = []): array
    {
        return [
            'name' => 'Palm Court Hotel',
            'city' => 'Annaba',
            'manager_name' => 'Amel Zidane',
            'manager_email' => 'amel.zidane@example.com',
            'contract_starts_on' => '2026-10-01',
            'contract_ends_on' => '2026-11-30',
            ...$overrides,
        ];
    }

    private function assertOneAuditRow(Hotel $hotel, string $action): AuditLog
    {
        $logs = AuditLog::query()
            ->where('auditable_type', $hotel->getMorphClass())
            ->where('auditable_id', $hotel->id)
            ->get();

        $this->assertCount(1, $logs, "Expected exactly one audit row for {$action}.");
        $log = $logs->first();
        $this->assertNotNull($log);
        $this->assertSame($action, $log->action);
        $this->assertSame($this->owner->id, $log->actor_id);
        $this->assertNotNull($log->ip);

        return $log;
    }
}

<?php

namespace Tests\Feature\Admin\Employees;

use App\Enums\AccountStatus;
use App\Enums\ReminderStatus;
use App\Enums\Role;
use App\Jobs\SendReminderEmail;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Reminder;
use App\Models\ReminderTemplate;
use App\Models\SeatQuota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Inertia\Support\SessionKey;
use Tests\TestCase;

/**
 * Every write on an employee account: create (inside the seat quota), edit,
 * activate, deactivate, reset password, bulk, remind and export, each with
 * its audit row (spec 0003 Part D; AUTH-02, AUTH-03, AUTH-07, AUTH-08,
 * SUB-02, SUB-03, REM-05, REM-07, REP-03, SEC-06).
 */
class EmployeeActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Hotel $hotel;

    private Department $reception;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->owner = User::factory()->superAdmin()->create();
        $this->hotel = Hotel::factory()->create(['name' => "La Gazelle d'Or"]);
        $this->reception = Department::factory()->create(['name' => 'Reception']);
        SeatQuota::factory()->create(['hotel_id' => $this->hotel->id, 'department_id' => $this->reception->id, 'allowed_seats' => 2]);
    }

    // -------------------------------------------------------------- create

    public function test_creating_an_employee_assigns_the_role_code_creator_and_consent()
    {
        $this->actingAs($this->owner)
            ->from(route('employees'))
            ->post(route('employees.store'), $this->validEmployee())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $employee = User::query()->where('username', 'amine.benali')->firstOrFail();

        $this->assertTrue($employee->hasRole(Role::Employee->value));
        $this->assertSame('Amine Ben Ali', $employee->name);
        $this->assertSame('amine.benali@hotel.dz', $employee->email);
        $this->assertSame($this->hotel->id, $employee->hotel_id);
        $this->assertSame($this->reception->id, $employee->department_id);
        $this->assertSame(AccountStatus::Active, $employee->status);
        $this->assertSame($this->owner->id, $employee->created_by);
        $this->assertMatchesRegularExpression('/^P-[A-Z0-9]{6}$/', (string) $employee->participant_code);
        $this->assertNotNull($employee->email_consent_at);
        $this->assertTrue(Hash::check('Secret-Pass-12', $employee->password));
        $this->assertOneAuditRow($employee, 'employee.created');
    }

    public function test_usernames_are_lowercased_and_consent_needs_an_email()
    {
        $this->actingAs($this->owner)->post(route('employees.store'), $this->validEmployee([
            'username' => 'Amine.BENALI',
            'email' => '',
            'allow_reminder_emails' => true,
        ]))->assertSessionHasNoErrors();

        $employee = User::query()->where('username', 'amine.benali')->firstOrFail();

        $this->assertNull($employee->email);
        $this->assertNull($employee->email_consent_at);
        $this->assertFalse($employee->consentsToReminders());
    }

    public function test_the_username_must_be_unique_and_well_formed()
    {
        User::factory()->employee()->create(['username' => 'taken']);

        $this->actingAs($this->owner)
            ->post(route('employees.store'), $this->validEmployee(['username' => 'taken']))
            ->assertSessionHasErrors('username');

        $this->actingAs($this->owner)
            ->post(route('employees.store'), $this->validEmployee(['username' => 'no spaces!']))
            ->assertSessionHasErrors('username');

        $this->actingAs($this->owner)
            ->post(route('employees.store'), $this->validEmployee(['password' => 'short']))
            ->assertSessionHasErrors('password');
    }

    public function test_the_seat_limit_refuses_the_next_account_with_the_message()
    {
        User::factory()->employee()->count(2)->create([
            'hotel_id' => $this->hotel->id,
            'department_id' => $this->reception->id,
            'status' => AccountStatus::Active,
        ]);

        $this->actingAs($this->owner)
            ->from(route('employees'))
            ->post(route('employees.store'), $this->validEmployee())
            ->assertRedirect(route('employees'))
            ->assertSessionHasErrors([
                'department_id' => "Seat limit reached for Reception at La Gazelle d'Or (2/2).",
            ]);

        $this->assertFalse(User::query()->where('username', 'amine.benali')->exists());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_an_inactive_account_may_be_created_at_the_seat_limit()
    {
        User::factory()->employee()->count(2)->create([
            'hotel_id' => $this->hotel->id,
            'department_id' => $this->reception->id,
            'status' => AccountStatus::Active,
        ]);

        $this->actingAs($this->owner)
            ->post(route('employees.store'), $this->validEmployee(['status' => 'inactive']))
            ->assertSessionHasNoErrors();

        $this->assertSame(AccountStatus::Inactive, User::query()->where('username', 'amine.benali')->firstOrFail()->status);
    }

    public function test_a_department_without_a_quota_is_refused()
    {
        $spa = Department::factory()->create(['name' => 'Spa']);

        $this->actingAs($this->owner)
            ->post(route('employees.store'), $this->validEmployee(['department_id' => $spa->id]))
            ->assertSessionHasErrors('department_id');
    }

    // ---------------------------------------------------------------- edit

    public function test_editing_an_employee_writes_the_diff_and_revokes_consent()
    {
        $employee = $this->employee(['email_consent_at' => now()]);

        $this->actingAs($this->owner)
            ->patch(route('employees.update', $employee), [
                'name' => 'Amine B.',
                'username' => $employee->username,
                'email' => $employee->email,
                'hotel_id' => $this->hotel->id,
                'department_id' => $this->reception->id,
                'status' => 'active',
                'allow_reminder_emails' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $employee->refresh();

        $this->assertSame('Amine B.', $employee->name);
        $this->assertNull($employee->email_consent_at);
        $audit = $this->assertOneAuditRow($employee, 'employee.updated');
        $this->assertSame(['from' => 'Amine Ben Ali', 'to' => 'Amine B.'], $audit->changes['attributes']['name'] ?? null);
    }

    public function test_editing_with_a_new_password_never_logs_the_hash()
    {
        $employee = $this->employee();

        $this->actingAs($this->owner)
            ->patch(route('employees.update', $employee), [
                'name' => $employee->name,
                'username' => $employee->username,
                'email' => $employee->email,
                'hotel_id' => $this->hotel->id,
                'department_id' => $this->reception->id,
                'status' => 'active',
                'password' => 'Another-Pass-12',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Another-Pass-12', $employee->fresh()?->password ?? ''));
        $audit = $this->assertOneAuditRow($employee, 'employee.updated');
        $this->assertSame('changed', $audit->changes['password'] ?? null);
        $this->assertArrayNotHasKey('password', $audit->changes['attributes'] ?? []);
    }

    // -------------------------------------------------------- status changes

    public function test_deactivate_frees_the_seat_and_activate_checks_it_again()
    {
        $first = $this->employee();
        $second = $this->employee(['username' => 'second', 'email' => 'second@hotel.dz']);

        $this->actingAs($this->owner)->post(route('employees.deactivate', $first))->assertRedirect();
        $this->assertSame(AccountStatus::Inactive, $first->fresh()?->status);
        $this->assertOneAuditRow($first, 'employee.deactivated');

        // The freed seat is taken by a third account.
        $this->actingAs($this->owner)->post(route('employees.store'), $this->validEmployee(['username' => 'third', 'email' => 'third@hotel.dz']))->assertSessionHasNoErrors();

        // Now the first cannot come back: the quota is full.
        $this->actingAs($this->owner)
            ->from(route('employees'))
            ->post(route('employees.activate', $first))
            ->assertSessionHasErrors('department_id');
        $this->assertSame(AccountStatus::Inactive, $first->fresh()?->status);

        // Free a seat again and it can.
        $this->actingAs($this->owner)->post(route('employees.deactivate', $second));
        $this->actingAs($this->owner)->post(route('employees.activate', $first))->assertSessionHasNoErrors();
        $this->assertSame(AccountStatus::Active, $first->fresh()?->status);
        $this->assertSame(1, AuditLog::query()->where('action', 'employee.activated')->count());
    }

    public function test_reset_password_flashes_the_new_password_once()
    {
        $employee = $this->employee();
        $before = $employee->password;

        $response = $this->actingAs($this->owner)
            ->post(route('employees.reset-password', $employee))
            ->assertRedirect();

        $credentials = $this->flashed('employeeCredentials');

        $this->assertIsArray($credentials);
        $this->assertSame($employee->id, $credentials['id']);
        $this->assertSame(12, strlen($credentials['password']));
        $this->assertTrue(Hash::check($credentials['password'], $employee->fresh()?->password ?? ''));
        $this->assertNotSame($before, $employee->fresh()?->password);
        $audit = $this->assertOneAuditRow($employee, 'employee.password_reset');
        $this->assertSame(['password' => 'changed'], $audit->changes);
        $this->assertInstanceOf(TestResponse::class, $response);
    }

    // ------------------------------------------------------------------ bulk

    public function test_bulk_deactivate_and_activate_touch_only_the_listed_rows()
    {
        $a = $this->employee();
        $b = $this->employee(['username' => 'b', 'email' => 'b@hotel.dz']);

        $this->actingAs($this->owner)
            ->post(route('employees.bulk'), ['action' => 'deactivate', 'ids' => [$a->id]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(AccountStatus::Inactive, $a->fresh()?->status);
        $this->assertSame(AccountStatus::Active, $b->fresh()?->status);

        $this->actingAs($this->owner)
            ->post(route('employees.bulk'), ['action' => 'activate', 'ids' => [$a->id, $b->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame(AccountStatus::Active, $a->fresh()?->status);
        $this->assertSame(2, AuditLog::count());

        $this->actingAs($this->owner)
            ->post(route('employees.bulk'), ['action' => 'activate', 'ids' => []])
            ->assertSessionHasErrors('ids');
    }

    // -------------------------------------------------------------- reminders

    public function test_reminders_go_to_consenting_employees_and_block_the_rest()
    {
        Queue::fake();
        ReminderTemplate::factory()->create(['name' => 'Training comeback reminder']);

        $consented = $this->employee(['email_consent_at' => now()]);
        $silent = $this->employee(['username' => 'silent', 'email' => 'silent@hotel.dz', 'email_consent_at' => null]);

        $this->actingAs($this->owner)
            ->post(route('employees.remind'), ['ids' => [$consented->id, $silent->id]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(ReminderStatus::Queued, Reminder::query()->where('user_id', $consented->id)->firstOrFail()->status);
        $blocked = Reminder::query()->where('user_id', $silent->id)->firstOrFail();
        $this->assertSame(ReminderStatus::Blocked, $blocked->status);
        $this->assertSame(Reminder::BLOCKED_NO_CONSENT, $blocked->blocked_reason);
        Queue::assertPushed(SendReminderEmail::class, 1);
        $this->assertSame(1, AuditLog::query()->where('action', 'reminders.sent')->count());
    }

    public function test_bulk_remind_uses_the_same_path()
    {
        Queue::fake();
        ReminderTemplate::factory()->create(['name' => 'Training comeback reminder']);
        $consented = $this->employee(['email_consent_at' => now()]);

        $this->actingAs($this->owner)
            ->post(route('employees.bulk'), ['action' => 'remind', 'ids' => [$consented->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Reminder::query()->where('user_id', $consented->id)->count());
        Queue::assertPushed(SendReminderEmail::class, 1);
    }

    // ---------------------------------------------------------------- export

    public function test_csv_export_streams_the_filtered_rows_and_is_audited()
    {
        $this->employee(['name' => 'Amine Ben Ali']);
        $this->employee(['name' => 'Sara Khelifi', 'username' => 'skhelifi', 'email' => 'sara@hotel.dz', 'status' => AccountStatus::Inactive]);

        $response = $this->actingAs($this->owner)
            ->get(route('employees.export', ['format' => 'csv', 'status' => 'inactive']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Sara Khelifi', $csv);
        $this->assertStringNotContainsString('Amine Ben Ali', $csv);
        $this->assertStringContainsString('Participant code', $csv);

        $audit = AuditLog::query()->where('action', 'employees.exported')->firstOrFail();
        $this->assertSame('csv', $audit->changes['format'] ?? null);
        $this->assertSame(1, $audit->changes['rows'] ?? null);
        $this->assertSame('inactive', $audit->changes['filters']['status'] ?? null);
    }

    public function test_xlsx_export_downloads_a_workbook()
    {
        $this->employee();

        $response = $this->actingAs($this->owner)
            ->get(route('employees.export', ['format' => 'xlsx']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
        $this->assertSame(1, AuditLog::query()->where('action', 'employees.exported')->count());

        $this->actingAs($this->owner)
            ->get(route('employees.export', ['format' => 'pdf']))
            ->assertSessionHasErrors('format');
    }

    // -------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validEmployee(array $overrides = []): array
    {
        return [
            'name' => 'Amine Ben Ali',
            'username' => 'amine.benali',
            'password' => 'Secret-Pass-12',
            'email' => 'amine.benali@hotel.dz',
            'hotel_id' => $this->hotel->id,
            'department_id' => $this->reception->id,
            'status' => 'active',
            'allow_reminder_emails' => true,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employee(array $attributes = []): User
    {
        return User::factory()->employee()->create([
            'name' => 'Amine Ben Ali',
            'username' => 'amine.benali',
            'email' => 'amine.benali@hotel.dz',
            'hotel_id' => $this->hotel->id,
            'department_id' => $this->reception->id,
            'status' => AccountStatus::Active,
            ...$attributes,
        ]);
    }

    private function assertOneAuditRow(User $employee, string $action): AuditLog
    {
        $rows = AuditLog::query()
            ->where('auditable_type', $employee->getMorphClass())
            ->where('auditable_id', $employee->id)
            ->where('action', $action)
            ->get();

        $this->assertCount(1, $rows, "Expected exactly one [{$action}] audit row.");
        $this->assertSame($this->owner->id, $rows->first()?->actor_id);

        return $rows->firstOrFail();
    }

    /**
     * Inertia flash data lives in the session under its own key until the
     * next Inertia response pulls it; read one entry back from there.
     */
    private function flashed(string $key): mixed
    {
        /** @var array<string, mixed> $data */
        $data = session()->get(SessionKey::FLASH_DATA, []);

        return $data[$key] ?? null;
    }
}

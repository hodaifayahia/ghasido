<?php

namespace App\Services\Employees;

use App\Enums\AccountStatus;
use App\Enums\EmployeeBulkAction;
use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Reminder;
use App\Models\ReminderTemplate;
use App\Models\User;
use App\Services\Hotels\SeatQuotaService;
use App\Services\Reminders\ReminderService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Every write to an employee account (AUTH-02, AUTH-03, AUTH-08, SUB-02,
 * SUB-03, REM-05, REM-07, SEC-06; spec 0003 Part D, Manage Employees).
 *
 * The controller validates through a form request and the policy, then hands
 * the clean data here. Each method changes the row and writes its audit row
 * inside one transaction, so a change cannot reach the database without its
 * trace (spec 0002, invariant 4). Nothing here deletes anything (DATA-10).
 *
 * The seat quota has teeth here and only here: creating an active account,
 * activating one, or moving one into another department goes through
 * SeatQuotaService::assertSeatAvailable(), which answers with a 422 naming
 * the department (SUB-02, SUB-03).
 */
class EmployeeService
{
    /** The name of the template a plain "Send reminder" uses (spec 0003 Part D). */
    public const DEFAULT_REMINDER_TEMPLATE = 'Training comeback reminder';

    /** Length of a generated password (the form's Generate button uses the same). */
    public const GENERATED_PASSWORD_LENGTH = 12;

    public function __construct(
        private readonly SeatQuotaService $seats,
        private readonly ReminderService $reminders,
    ) {}

    /**
     * Create an employee account inside the hotel's seat quota (SUB-02).
     *
     * The account gets the employee role, a participant code for the
     * anonymised export (REP-07) and the creator's id (AUTH-03). Consent is
     * a dated timestamp, written only when the box was ticked AND an email was
     * given (REM-05, PRIV-02).
     *
     * @param  array{name: string, username: string, email: string|null, hotel_id: int, department_id: int, status: string, allow_reminder_emails: bool, password: string}  $data
     */
    public function create(array $data, User $creator): User
    {
        $hotel = Hotel::query()->withoutGlobalScopes()->findOrFail($data['hotel_id']);
        $department = Department::query()->findOrFail($data['department_id']);
        $status = AccountStatus::from($data['status']);
        $defaultPoints = $hotel->subscriptionPlan()->value('points_per_employee') ?? 2000;

        if ($status === AccountStatus::Active) {
            $this->seats->assertSeatAvailable($hotel, $department);
        }

        return DB::transaction(function () use ($data, $creator, $status, $defaultPoints): User {
            $employee = new User;
            $employee->fill([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'],
                'hotel_id' => $data['hotel_id'],
                'department_id' => $data['department_id'],
                'status' => $status,
                'email_consent_at' => $this->consentAt($data['allow_reminder_emails'], $data['email'], null),
                'participant_code' => User::generateParticipantCode(),
                'created_by' => $creator->id,
                'ai_points_allocated' => (int) $defaultPoints,
            ]);
            $employee->save();
            $employee->setRole(Role::Employee);

            AuditLog::record($employee, 'employee.created', [
                'created' => $employee->only(['name', 'username', 'email', 'hotel_id', 'department_id', 'status', 'participant_code']),
            ]);

            return $employee;
        });
    }

    /**
     * Edit an account. A move into another department, or a flip to active,
     * re-checks the seat quota there first (SUB-03).
     *
     * @param  array{name: string, username: string, email: string|null, hotel_id: int, department_id: int, status: string, allow_reminder_emails: bool, password?: string}  $data
     */
    public function update(User $employee, array $data): User
    {
        $status = AccountStatus::from($data['status']);
        $becomesActive = $status === AccountStatus::Active
            && ($employee->status !== AccountStatus::Active
                || $employee->hotel_id !== $data['hotel_id']
                || $employee->department_id !== $data['department_id']);

        if ($becomesActive) {
            $this->seats->assertSeatAvailable(
                Hotel::query()->withoutGlobalScopes()->findOrFail($data['hotel_id']),
                Department::query()->findOrFail($data['department_id']),
            );
        }

        return $this->change($employee, 'employee.updated', function (User $employee) use ($data, $status): void {
            $employee->fill([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'hotel_id' => $data['hotel_id'],
                'department_id' => $data['department_id'],
                'status' => $status,
                'email_consent_at' => $this->consentAt($data['allow_reminder_emails'], $data['email'], $employee->email_consent_at),
            ]);
        }, [], $data['password'] ?? null);
    }

    /**
     * Reactivate: the account takes a seat again, so the quota is checked
     * (SUB-02). Already active is a no-op with no audit row.
     */
    public function activate(User $employee): User
    {
        if ($employee->status === AccountStatus::Active) {
            return $employee;
        }

        $employee->loadMissing(['hotel' => fn ($query) => $query->withoutGlobalScopes(), 'department']);

        if ($employee->hotel !== null && $employee->department !== null) {
            $this->seats->assertSeatAvailable($employee->hotel, $employee->department);
        }

        return $this->change($employee, 'employee.activated', function (User $employee): void {
            $employee->status = AccountStatus::Active;
        });
    }

    /**
     * Deactivate: access stops, the seat is freed, every row stays (AUTH-08,
     * DATA-10). Already inactive is a no-op with no audit row.
     */
    public function deactivate(User $employee): User
    {
        if ($employee->status === AccountStatus::Inactive) {
            return $employee;
        }

        return $this->change($employee, 'employee.deactivated', function (User $employee): void {
            $employee->status = AccountStatus::Inactive;
        });
    }

    /**
     * Set a fresh generated password and return it in clear, once, for the
     * admin to hand over (AUTH-07). Only the fact of the reset is audited.
     */
    public function resetPassword(User $employee): string
    {
        $password = self::generatePassword();

        $this->change($employee, 'employee.password_reset', static function (): void {}, [], $password);

        return $password;
    }

    /**
     * Activate or deactivate the checked rows. Returns how many changed and
     * how many were refused by their seat quota (the toast reads both).
     *
     * @param  Collection<int, User>  $employees
     * @return array{changed: int, skipped: int, blocked: int}
     */
    public function bulk(EmployeeBulkAction $action, Collection $employees, User $actor): array
    {
        if ($action === EmployeeBulkAction::Remind) {
            $result = $this->remind($employees, $actor);

            return ['changed' => $result['queued'], 'skipped' => 0, 'blocked' => $result['blocked']];
        }

        $changed = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            $before = $employee->status;

            try {
                $action === EmployeeBulkAction::Activate
                    ? $this->activate($employee)
                    : $this->deactivate($employee);
            } catch (ValidationException) {
                $skipped++;

                continue;
            }

            if ($employee->status !== $before) {
                $changed++;
            }
        }

        return ['changed' => $changed, 'skipped' => $skipped, 'blocked' => 0];
    }

    /**
     * Email the default (or the named) template to these employees through
     * ReminderService, which blocks anyone without consent or an address
     * (REM-05, REM-07). Returns the counts the toast summarises.
     *
     * @param  Collection<int, User>  $employees
     * @return array{queued: int, blocked: int, template: string}
     */
    public function remind(Collection $employees, User $sender, ?ReminderTemplate $template = null): array
    {
        $template ??= $this->defaultTemplate();

        $sent = $this->reminders->send($employees, $template, ReminderChannel::Email, $sender);

        $blocked = $sent->filter(fn (Reminder $reminder): bool => $reminder->status === ReminderStatus::Blocked)->count();

        return [
            'queued' => $sent->count() - $blocked,
            'blocked' => $blocked,
            'template' => $template->name,
        ];
    }

    /**
     * The template a plain Send reminder uses: the seeded comeback reminder,
     * else the first active template.
     */
    public function defaultTemplate(): ReminderTemplate
    {
        return ReminderTemplate::query()->active()->where('name', self::DEFAULT_REMINDER_TEMPLATE)->first()
            ?? ReminderTemplate::query()->active()->orderBy('id')->firstOrFail();
    }

    /**
     * Twelve characters from an unambiguous alphabet (no 0/O, 1/l/I), with at
     * least one digit, so it passes the password rule and can be read aloud.
     */
    public static function generatePassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $length = self::GENERATED_PASSWORD_LENGTH;

        do {
            $password = '';

            for ($i = 0; $i < $length; $i++) {
                $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (! Str::contains($password, ['2', '3', '4', '5', '6', '7', '8', '9']));

        return $password;
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Consent as a dated timestamp (PRIV-02, REM-05): set the first time the
     * box is ticked with an address on file, kept while it stays ticked,
     * cleared when unticked or when the address is removed.
     */
    private function consentAt(bool $allowed, ?string $email, ?CarbonInterface $current): ?CarbonInterface
    {
        if (! $allowed || $email === null) {
            return null;
        }

        return $current ?? Date::now();
    }

    /**
     * Apply one change and its audit row in one transaction, with the diff
     * read from Eloquent while the attributes are still dirty.
     *
     * A new password is set AFTER the audit row is written, so the log says
     * the password changed and never carries the hash (SEC-06).
     *
     * @param  callable(User): void  $mutate
     * @param  array<string, mixed>  $extra
     */
    private function change(User $employee, string $action, callable $mutate, array $extra = [], ?string $password = null): User
    {
        return DB::transaction(function () use ($employee, $action, $mutate, $extra, $password): User {
            $mutate($employee);

            AuditLog::record($employee, $action, $extra + ($password === null ? [] : ['password' => 'changed']));

            if ($password !== null) {
                $employee->password = $password;
            }

            $employee->save();

            return $employee;
        });
    }
}

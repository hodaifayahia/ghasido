<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Hotel;
use App\Models\User;

/**
 * Who may manage which employee accounts (ROLE-01, ROLE-02, SUB-02, SEC-01;
 * spec 0003 Part D, Manage Employees).
 *
 * Two questions every method answers, in this order: does the actor hold the
 * capability, and is this account theirs to touch. "Theirs" means an account
 * holding the EMPLOYEE role in the actor's own hotel: an Admin or Manager
 * never reads or edits another hotel's staff, and never touches another
 * Admin, Manager or Super Admin through this screen, whatever the hotel.
 *
 * The Super Admin never reaches any of this: Gate::before in
 * AppServiceProvider returns true for them first (spec 0001, AC-2). Resolved
 * by Laravel's policy discovery for App\Models\User, so the model file is
 * left alone.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::EmployeesView->value);
    }

    public function view(User $actor, User $employee): bool
    {
        return $actor->can(Permission::EmployeesView->value)
            && $this->managesAccount($actor, $employee);
    }

    /**
     * May the actor create employee accounts at all? Which hotel they may
     * create them in is createIn(), asked by the form request.
     */
    public function create(User $actor): bool
    {
        // Adding an account is its own capability: a Manager holds
        // EmployeesManage (edit) but not EmployeesCreate (client decision).
        return $actor->can(Permission::EmployeesCreate->value);
    }

    /**
     * Create an account in this hotel. An Admin or Manager may only fill their own
     * hotel's seats (SUB-02); the seat quota itself is checked by
     * SeatQuotaService, which answers 422, not 403.
     *
     * Called as `can('createIn', [User::class, $hotel])`: the Gate picks the
     * policy from the first argument, so naming the class keeps the check
     * here rather than on HotelPolicy.
     */
    public function createIn(User $actor, Hotel $hotel): bool
    {
        return $this->create($actor) && $this->belongsTo($actor, $hotel);
    }

    /**
     * Assign an employee to this hotel through an edit. Editing is
     * EmployeesManage, which a Manager holds; moving the account to a foreign
     * hotel is still refused by belongsTo (ROLE-02). Kept apart from createIn
     * so a Manager who may edit but not add is not blocked from a normal edit.
     *
     * Called as `can('assignToHotel', [User::class, $hotel])`.
     */
    public function assignToHotel(User $actor, Hotel $hotel): bool
    {
        return $actor->can(Permission::EmployeesManage->value) && $this->belongsTo($actor, $hotel);
    }

    public function update(User $actor, User $employee): bool
    {
        return $actor->can(Permission::EmployeesManage->value)
            && $this->managesAccount($actor, $employee);
    }

    /**
     * Safe delete of an employee account: the same reach as editing it;
     * refused while the account holds any training record.
     */
    public function delete(User $actor, User $employee): bool
    {
        return $this->update($actor, $employee);
    }

    public function activate(User $actor, User $employee): bool
    {
        return $this->update($actor, $employee);
    }

    public function deactivate(User $actor, User $employee): bool
    {
        return $this->update($actor, $employee);
    }

    public function resetPassword(User $actor, User $employee): bool
    {
        return $this->update($actor, $employee);
    }

    /**
     * Send a reminder to this employee (REM-07). Consent is not a permission
     * question: ReminderService blocks the row when it is missing (REM-05).
     */
    public function remind(User $actor, User $employee): bool
    {
        return $this->update($actor, $employee);
    }

    /**
     * Export the directory. Which rows are exported is decided by the
     * directory query, which scopes an Admin or Manager to their hotel (REP-08).
     */
    public function export(User $actor): bool
    {
        return $actor->can(Permission::EmployeesView->value);
    }

    /**
     * Read or export the employees of this hotel: an Admin or Manager's own only.
     */
    public function viewHotel(User $actor, Hotel $hotel): bool
    {
        return $actor->can(Permission::EmployeesView->value)
            && $this->belongsTo($actor, $hotel);
    }

    /**
     * Is this account an employee of the actor's own hotel?
     *
     * An Admin or Manager with no hotel manages nothing, which is the safe answer, and
     * a target with no hotel is nobody's to manage either.
     */
    private function managesAccount(User $actor, User $employee): bool
    {
        return $employee->hotel_id !== null
            && $actor->hotel_id !== null
            && $actor->hotel_id === $employee->hotel_id
            && $employee->hasRole(Role::Employee->value);
    }

    private function belongsTo(User $actor, Hotel $hotel): bool
    {
        return $actor->hotel_id !== null && $actor->hotel_id === $hotel->id;
    }
}

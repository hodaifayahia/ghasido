<?php

namespace App\Enums;

/**
 * The fixed Guesvia account roles (ROLE-01, spec 0001).
 *
 * Employees are learner accounts, while Super Admin, Admin and Manager are
 * the three management roles requested for the hotel back office. A user
 * holds at most one role. The permission matrix below is the single source
 * the seeder reads, so a role's abilities change in one place.
 */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Manager = 'manager';
    case Employee = 'employee';

    /**
     * The permissions this role holds.
     *
     * The Super Admin holds every row in addition to the Gate::before override
     * (spec 0001, invariant 3): an empty permission list would make the
     * sidebar filter hide every item from the one person who sees everything.
     *
     * The Employee holds none of these admin permissions: learner routes use
     * the Employee role as their separate server-side boundary.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => Permission::cases(),
            self::Admin => [
                // Admins can open their own hotel and manage its departments
                // and employee accounts, but cannot manage the platform or
                // read another hotel's records (ROLE-02, SEC-01).
                Permission::HotelsView,
                Permission::DepartmentsView,
                Permission::DepartmentsManage,
                Permission::EmployeesView,
                Permission::EmployeesCreate,
                Permission::EmployeesManage,
                Permission::LessonsView,
                Permission::MessagesView,
                Permission::MessagesManage,
                Permission::ReportsView,
                Permission::ReportsExport,
            ],
            self::Manager => [
                // Managers may open their own hotel and EDIT its existing
                // employee accounts, but by client decision they may not add
                // accounts (no EmployeesCreate), build content, or open
                // Reports & Export (no ReportsView/Export). This narrows the
                // manager row of SUB-02 and REP-08.
                Permission::HotelsView,
                Permission::DepartmentsView,
                Permission::EmployeesView,
                Permission::EmployeesManage,
                Permission::AiPointsManage,
                Permission::LessonsView,
                Permission::MessagesView,
                Permission::MessagesManage,
            ],
            self::Employee => [],
        };
    }

    /**
     * The permission names this role holds, for syncPermissions().
     *
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return array_map(
            static fn (Permission $permission): string => $permission->value,
            $this->permissions(),
        );
    }

    /**
     * A human label for this role, shown on the Roles & Permissions screen.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Hotel Admin',
            self::Manager => 'Hotel Manager',
            self::Employee => 'Employee',
        };
    }

    /**
     * Whether a role name is one of the four fixed system roles. Custom roles
     * created from the Roles & Permissions screen are anything else, and only
     * those may be renamed or deleted.
     */
    public static function isSystem(string $name): bool
    {
        return self::tryFrom($name) !== null;
    }
}

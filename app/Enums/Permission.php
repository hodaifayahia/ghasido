<?php

namespace App\Enums;

/**
 * Every capability the platform checks (spec 0001).
 *
 * A permission name exists only as a case here: no bare permission string may
 * appear in a route file, controller, policy or Vue file, so a typo fails at
 * static analysis rather than as a silent 403 (spec 0001, invariant 5).
 *
 * Permissions reach a user only through a role. `model_has_permissions` stays
 * empty and nothing calls givePermissionTo() on a User (invariant 2).
 */
enum Permission: string
{
    case HotelsView = 'hotels.view';
    case HotelsManage = 'hotels.manage';
    case SubscriptionsManage = 'subscriptions.manage';
    case HotelsApprove = 'hotels.approve';
    case DepartmentsView = 'departments.view';
    case DepartmentsManage = 'departments.manage';
    case EmployeesView = 'employees.view';
    case EmployeesCreate = 'employees.create';
    case EmployeesManage = 'employees.manage';
    case AiPointsManage = 'ai_points.manage';
    case LessonsView = 'lessons.view';
    case LessonsManage = 'lessons.manage';
    case ScenariosView = 'scenarios.view';
    case ScenariosManage = 'scenarios.manage';
    case TestsView = 'tests.view';
    case TestsManage = 'tests.manage';
    case MessagesView = 'messages.view';
    case MessagesManage = 'messages.manage';
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';
    case ReportsExportAnonymised = 'reports.export_anonymised';
    case TranscriptsView = 'transcripts.view';
    case ProgressReset = 'progress.reset';
    case RolesView = 'roles.view';
    case RolesManage = 'roles.manage';
    case LandingManage = 'landing.manage';
    case TrainingSelf = 'training.self';

    /**
     * The `permission:` middleware argument for this capability.
     *
     * Route files write `Permission::HotelsView->middleware()` rather than the
     * string 'permission:hotels.view'.
     */
    public function middleware(): string
    {
        return 'permission:'.$this->value;
    }

    /**
     * A human label for this capability, shown on the Roles & Permissions
     * screen (spec 0001 follow-up: the permission list doubles as a plain
     * description of what each role may do).
     */
    public function label(): string
    {
        return match ($this) {
            self::HotelsView => 'View hotels',
            self::HotelsManage => 'Manage hotels',
            self::SubscriptionsManage => 'Manage subscription plans',
            self::HotelsApprove => 'Approve hotel sign-ups',
            self::DepartmentsView => 'View departments',
            self::DepartmentsManage => 'Manage departments',
            self::EmployeesView => 'View employees',
            self::EmployeesCreate => 'Add employees',
            self::EmployeesManage => 'Manage employees',
            self::AiPointsManage => 'Manage employee AI points',
            self::LessonsView => 'View lessons & content',
            self::LessonsManage => 'Manage lessons & content',
            self::ScenariosView => 'View AI scenarios',
            self::ScenariosManage => 'Manage AI scenarios',
            self::TestsView => 'View tests',
            self::TestsManage => 'Manage tests',
            self::MessagesView => 'View messages & reminders',
            self::MessagesManage => 'Manage messages & reminders',
            self::ReportsView => 'View reports',
            self::ReportsExport => 'Export reports',
            self::ReportsExportAnonymised => 'Export anonymised research data',
            self::TranscriptsView => 'View AI transcripts & recordings',
            self::ProgressReset => 'Reset employee progress',
            self::RolesView => 'View roles & permissions',
            self::RolesManage => 'Manage roles & permissions',
            self::LandingManage => 'Manage the public landing page',
            self::TrainingSelf => 'Access own training (learn as an employee)',
        };
    }

    /**
     * The screen this capability belongs to, used to group the permission
     * checkboxes on the Roles & Permissions screen.
     */
    public function group(): string
    {
        return match ($this) {
            self::HotelsView, self::HotelsManage, self::HotelsApprove => 'Hotels',
            self::SubscriptionsManage => 'Subscriptions',
            self::DepartmentsView, self::DepartmentsManage => 'Departments',
            self::EmployeesView, self::EmployeesCreate, self::EmployeesManage => 'Employees',
            self::AiPointsManage => 'AI Points',
            self::LessonsView, self::LessonsManage => 'Lessons & Content',
            self::ScenariosView, self::ScenariosManage => 'AI Scenarios',
            self::TestsView, self::TestsManage => 'Pre-test & Post-test',
            self::MessagesView, self::MessagesManage => 'Messages & Reminders',
            self::ReportsView, self::ReportsExport, self::ReportsExportAnonymised => 'Reports & Export',
            self::TranscriptsView => 'Transcripts & Recordings',
            self::ProgressReset => 'Employee Progress',
            self::RolesView, self::RolesManage => 'Roles & Permissions',
            self::LandingManage => 'Landing Page',
            self::TrainingSelf => 'Training',
        };
    }

    /**
     * Every permission name, for the seeder and for the Super Admin grant.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(
            static fn (self $permission): string => $permission->value,
            self::cases(),
        );
    }
}

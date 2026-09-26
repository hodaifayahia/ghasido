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
    case ScoresOverride = 'scores.override';
    case ProgressReset = 'progress.reset';
    case RolesView = 'roles.view';
    case RolesManage = 'roles.manage';
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
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
            self::HotelsView => __('View hotels'),
            self::HotelsManage => __('Manage hotels'),
            self::SubscriptionsManage => __('Manage subscription plans'),
            self::HotelsApprove => __('Approve hotel sign-ups'),
            self::DepartmentsView => __('View departments'),
            self::DepartmentsManage => __('Manage departments'),
            self::EmployeesView => __('View employees'),
            self::EmployeesCreate => __('Add employees'),
            self::EmployeesManage => __('Manage employees'),
            self::AiPointsManage => __('Manage employee AI points'),
            self::LessonsView => __('View lessons & content'),
            self::LessonsManage => __('Manage lessons & content'),
            self::ScenariosView => __('View AI scenarios'),
            self::ScenariosManage => __('Manage AI scenarios'),
            self::TestsView => __('View tests'),
            self::TestsManage => __('Manage tests'),
            self::MessagesView => __('View messages & reminders'),
            self::MessagesManage => __('Manage messages & reminders'),
            self::ReportsView => __('View reports'),
            self::ReportsExport => __('Export reports'),
            self::ReportsExportAnonymised => __('Export anonymised research data'),
            self::TranscriptsView => __('View AI transcripts & recordings'),
            self::ScoresOverride => __('Override and re-grade AI scores'),
            self::ProgressReset => __('Reset employee progress'),
            self::RolesView => __('View roles & permissions'),
            self::RolesManage => __('Manage roles & permissions'),
            self::UsersView => __('View app users'),
            self::UsersManage => __('Add and manage app users'),
            self::LandingManage => __('Manage the public landing page'),
            self::TrainingSelf => __('Access own training (learn as an employee)'),
        };
    }

    /**
     * The screen this capability belongs to, used to group the permission
     * checkboxes on the Roles & Permissions screen.
     */
    public function group(): string
    {
        return match ($this) {
            self::HotelsView, self::HotelsManage, self::HotelsApprove => __('Hotels'),
            self::SubscriptionsManage => __('Subscriptions'),
            self::DepartmentsView, self::DepartmentsManage => __('Departments'),
            self::EmployeesView, self::EmployeesCreate, self::EmployeesManage => __('Employees'),
            self::AiPointsManage => __('AI Points'),
            self::LessonsView, self::LessonsManage => __('Lessons & Content'),
            self::ScenariosView, self::ScenariosManage => __('AI Scenarios'),
            self::TestsView, self::TestsManage => __('Pre-test & Post-test'),
            self::MessagesView, self::MessagesManage => __('Messages & Reminders'),
            self::ReportsView, self::ReportsExport, self::ReportsExportAnonymised => __('Reports & Export'),
            self::TranscriptsView => __('Transcripts & Recordings'),
            self::ScoresOverride => __('Reports & Export'),
            self::ProgressReset => __('Employee Progress'),
            self::RolesView, self::RolesManage => __('Roles & Permissions'),
            self::UsersView, self::UsersManage => __('Users'),
            self::LandingManage => __('Landing Page'),
            self::TrainingSelf => __('Training'),
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

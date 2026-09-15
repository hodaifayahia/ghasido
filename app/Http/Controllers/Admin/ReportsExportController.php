<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Super Admin reports and export screen (REP-01, REP-02, REP-03,
 * REP-04, REP-05, REP-06, REP-07, TEST-10, AIE-04, ADM-02).
 *
 * Reporting queries and exports are not modelled yet, so this screen returns
 * sample data shaped to the approved mockup for a UI-first build.
 */
class ReportsExportController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/ReportsExport', [
            'filters' => [
                'range' => 'jul-aug-2026',
                'hotel' => 'la-gazelle-dor',
                'department' => 'all-departments',
                'employee' => 'all-employees',
                'activityType' => 'all-activities',
                'completionStatus' => 'all',
                'ranges' => [
                    [
                        'value' => 'jul-aug-2026',
                        'label' => __('1 Jul 2026 - 31 Aug 2026'),
                    ],
                    [
                        'value' => 'aug-2026',
                        'label' => __('1 Aug 2026 - 31 Aug 2026'),
                    ],
                ],
                'hotels' => [
                    ['value' => 'la-gazelle-dor', 'label' => 'La Gazelle d\'Or'],
                    ['value' => 'aurassi', 'label' => __('Hotel El Aurassi')],
                ],
                'departments' => [
                    [
                        'value' => 'all-departments',
                        'label' => __('All Departments'),
                    ],
                    ['value' => 'reception', 'label' => __('Reception')],
                    ['value' => 'spa', 'label' => __('Spa')],
                    ['value' => 'housekeeping', 'label' => __('Housekeeping')],
                    [
                        'value' => 'food-beverage',
                        'label' => __('Food & Beverage'),
                    ],
                ],
                'employees' => [
                    [
                        'value' => 'all-employees',
                        'label' => __('All Employees'),
                    ],
                    ['value' => 'amina-saadi', 'label' => 'Amina Saadi'],
                    ['value' => 'karim-ben-ali', 'label' => 'Karim Ben Ali'],
                ],
                'activityTypes' => [
                    [
                        'value' => 'all-activities',
                        'label' => __('All Activities'),
                    ],
                    ['value' => 'lessons', 'label' => __('Lessons')],
                    ['value' => 'tests', 'label' => __('Pre/Post Tests')],
                    ['value' => 'roleplay', 'label' => __('AI Scenarios')],
                ],
                'completionStatuses' => [
                    ['value' => 'all', 'label' => __('All')],
                    ['value' => 'active', 'label' => __('Active')],
                    ['value' => 'completed', 'label' => __('Completed')],
                    ['value' => 'inactive', 'label' => __('Inactive')],
                ],
            ],
            'stats' => [
                [
                    'key' => 'totalEmployees',
                    'value' => 72,
                    'label' => __('Total Employees'),
                    'detail' => __('5 Departments'),
                ],
                [
                    'key' => 'activeAccounts',
                    'value' => 68,
                    'label' => __('Active Accounts'),
                    'detail' => __('94%'),
                ],
                [
                    'key' => 'completedPretest',
                    'value' => 54,
                    'label' => __('Completed Pre-test'),
                    'detail' => __('75%'),
                ],
                [
                    'key' => 'completedPosttest',
                    'value' => 42,
                    'label' => __('Completed Post-test'),
                    'detail' => __('58%'),
                ],
                [
                    'key' => 'completedAiScenarios',
                    'value' => 36,
                    'label' => __('Completed AI Scenarios'),
                    'detail' => __('50%'),
                ],
                [
                    'key' => 'completedLessons',
                    'value' => 28,
                    'label' => __('Completed All Lessons'),
                    'detail' => __('39%'),
                ],
            ],
            'prePost' => [
                ['label' => __('Reception'), 'first' => 52, 'second' => 74],
                ['label' => __('Spa'), 'first' => 49, 'second' => 70],
                ['label' => __('Housekeeping'), 'first' => 48, 'second' => 69],
                ['label' => __('Food & Beverage'), 'first' => 50, 'second' => 66],
                ['label' => __('Kitchen'), 'first' => 47, 'second' => 71],
            ],
            'completion' => [
                'overall' => 39,
                'completed' => 39,
                'inProgress' => 36,
                'notStarted' => 25,
            ],
            'aiPerformance' => [
                ['label' => __('Reception'), 'value' => 81],
                ['label' => __('Spa'), 'value' => 60],
                ['label' => __('Housekeeping'), 'value' => 46],
                ['label' => __('F&B'), 'value' => 53],
                ['label' => __('Kitchen'), 'value' => 57],
            ],
            'activity' => [
                'total' => 72,
                'activeThisWeek' => 48,
                'activeThisMonth' => 16,
                'inactive' => 8,
            ],
            'tabs' => [
                ['key' => 'employeeResults', 'label' => __('Employee Results')],
                ['key' => 'detailedAnswers', 'label' => __('Detailed Answers')],
                ['key' => 'roleplayLogs', 'label' => __('AI Role-play Logs')],
                ['key' => 'lessonProgress', 'label' => __('Lesson Progress')],
                ['key' => 'comparison', 'label' => __('Pre/Post Comparison')],
                ['key' => 'downloadCenter', 'label' => __('Download Center')],
            ],
            'activeTab' => 'employeeResults',
            'rows' => [
                [
                    'id' => 1,
                    'rank' => 1,
                    'initials' => 'AS',
                    'name' => 'Amina Saadi',
                    'department' => __('Reception'),
                    'preScore' => 60,
                    'postScore' => 85,
                    'lessonsCompleted' => 12,
                    'lessonsTotal' => 15,
                    'scenariosCompleted' => 8,
                    'scenariosTotal' => 10,
                    'lastActivity' => '28 Aug 2026',
                    'status' => 'active',
                    'statusLabel' => __('Active'),
                ],
                [
                    'id' => 2,
                    'rank' => 2,
                    'initials' => 'KB',
                    'name' => 'Karim Ben Ali',
                    'department' => __('Reception'),
                    'preScore' => 45,
                    'postScore' => 70,
                    'lessonsCompleted' => 10,
                    'lessonsTotal' => 15,
                    'scenariosCompleted' => 6,
                    'scenariosTotal' => 10,
                    'lastActivity' => '27 Aug 2026',
                    'status' => 'active',
                    'statusLabel' => __('Active'),
                ],
                [
                    'id' => 3,
                    'rank' => 3,
                    'initials' => 'SM',
                    'name' => 'Sofia Merad',
                    'department' => __('Spa'),
                    'preScore' => 50,
                    'postScore' => 78,
                    'lessonsCompleted' => 14,
                    'lessonsTotal' => 15,
                    'scenariosCompleted' => 9,
                    'scenariosTotal' => 10,
                    'lastActivity' => '28 Aug 2026',
                    'status' => 'active',
                    'statusLabel' => __('Active'),
                ],
                [
                    'id' => 4,
                    'rank' => 4,
                    'initials' => 'HZ',
                    'name' => 'Hicham Zitoune',
                    'department' => __('Housekeeping'),
                    'preScore' => 30,
                    'postScore' => 60,
                    'lessonsCompleted' => 8,
                    'lessonsTotal' => 15,
                    'scenariosCompleted' => 5,
                    'scenariosTotal' => 10,
                    'lastActivity' => '25 Aug 2026',
                    'status' => 'in_progress',
                    'statusLabel' => __('In progress'),
                ],
                [
                    'id' => 5,
                    'rank' => 5,
                    'initials' => 'NG',
                    'name' => 'Nadia Gacem',
                    'department' => __('Food & Beverage'),
                    'preScore' => 55,
                    'postScore' => 72,
                    'lessonsCompleted' => 11,
                    'lessonsTotal' => 15,
                    'scenariosCompleted' => 7,
                    'scenariosTotal' => 10,
                    'lastActivity' => '27 Aug 2026',
                    'status' => 'active',
                    'statusLabel' => __('Active'),
                ],
                [
                    'id' => 6,
                    'rank' => 6,
                    'initials' => 'YK',
                    'name' => 'Youssef Khaldi',
                    'department' => __('Kitchen'),
                    'preScore' => 40,
                    'postScore' => 65,
                    'lessonsCompleted' => 9,
                    'lessonsTotal' => 15,
                    'scenariosCompleted' => 4,
                    'scenariosTotal' => 10,
                    'lastActivity' => '22 Aug 2026',
                    'status' => 'inactive',
                    'statusLabel' => __('Inactive (7 days)'),
                ],
                [
                    'id' => 7,
                    'rank' => 7,
                    'initials' => 'ST',
                    'name' => 'Samira Tabi',
                    'department' => __('Spa'),
                    'preScore' => 65,
                    'postScore' => 88,
                    'lessonsCompleted' => 15,
                    'lessonsTotal' => 15,
                    'scenariosCompleted' => 10,
                    'scenariosTotal' => 10,
                    'lastActivity' => '28 Aug 2026',
                    'status' => 'completed',
                    'statusLabel' => __('Completed'),
                ],
            ],
            'pagination' => [
                'from' => 1,
                'to' => 7,
                'total' => 72,
                'currentPage' => 1,
                'lastPage' => 11,
                'pages' => [1, 2, 3, 4, 5, 'ellipsis', 11],
                'perPageOptions' => [7, 10, 20],
                'currentPerPage' => 7,
            ],
            'search' => '',
            'includeDetailedAnswers' => true,
            'exportActions' => [
                [
                    'id' => 'excel',
                    'label' => __('Export to Excel (.xlsx)'),
                    'tone' => 'excel',
                ],
                [
                    'id' => 'csv',
                    'label' => __('Export to CSV (.csv)'),
                    'tone' => 'brand',
                ],
                [
                    'id' => 'pdf',
                    'label' => __('Export PDF Report'),
                    'tone' => 'danger',
                ],
            ],
        ]);
    }
}
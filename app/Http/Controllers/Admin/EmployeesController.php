<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Super Admin manage-employees screen (SUB-02, ORG-03, REM-07, REP-03,
 * ADM-02).
 *
 * Hotels, departments and employee accounts are not modelled yet, so this
 * page returns the approved mockup's sample data. The prop shapes are the real
 * contract for the eventual backend swap.
 */
class EmployeesController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/Employees', [
            'stats' => [
                [
                    'key' => 'totalEmployees',
                    'value' => 80,
                    'label' => __('Total Employees'),
                ],
                [
                    'key' => 'activeAccounts',
                    'value' => 62,
                    'label' => __('Active Accounts'),
                ],
                [
                    'key' => 'inactiveAccounts',
                    'value' => 6,
                    'label' => __('Inactive Accounts'),
                ],
                [
                    'key' => 'startedTraining',
                    'value' => 44,
                    'label' => __('Started Training'),
                    'detail' => '55.0%',
                ],
                [
                    'key' => 'completedTraining',
                    'value' => 18,
                    'label' => __('Completed Training'),
                    'detail' => '22.5%',
                ],
                [
                    'key' => 'notStarted',
                    'value' => 18,
                    'label' => __('Not Started'),
                    'detail' => '22.5%',
                ],
            ],
            'filters' => [
                'search' => '',
                'hotel' => 'all-hotels',
                'department' => 'all-departments',
                'status' => 'all-statuses',
                'hotels' => [
                    ['value' => 'all-hotels', 'label' => __('All Hotels')],
                    ['value' => 'la-gazelle-dor', 'label' => 'La Gazelle d\'Or'],
                    ['value' => 'aurassi', 'label' => __('Hotel El Aurassi')],
                    ['value' => 'sheraton-club', 'label' => __('Sheraton Club des Pins')],
                ],
                'departments' => [
                    [
                        'value' => 'all-departments',
                        'label' => __('All Departments'),
                    ],
                    ['value' => 'reception', 'label' => __('Reception')],
                    ['value' => 'spa', 'label' => __('Spa')],
                    ['value' => 'housekeeping', 'label' => __('Housekeeping')],
                    ['value' => 'food-service', 'label' => __('Food Service')],
                    ['value' => 'kitchen', 'label' => __('Kitchen')],
                    [
                        'value' => 'technical-services',
                        'label' => __('Technical Services'),
                    ],
                ],
                'statuses' => [
                    ['value' => 'all-statuses', 'label' => __('All Statuses')],
                    ['value' => 'completed', 'label' => __('Completed')],
                    ['value' => 'in-progress', 'label' => __('In Progress')],
                    ['value' => 'not-started', 'label' => __('Not Started')],
                    ['value' => 'inactive', 'label' => __('Inactive')],
                ],
            ],
            'employees' => [
                [
                    'id' => 1,
                    'rank' => 1,
                    'name' => 'Amine Ben Ali',
                    'username' => 'abenali',
                    'hotel' => 'La Gazelle d\'Or',
                    'department' => __('Reception'),
                    'email' => 'amine.benali@hotel.dz',
                    'progress' => 100,
                    'status' => 'completed',
                    'lastLogin' => '06 Sep 2026',
                ],
                [
                    'id' => 2,
                    'rank' => 2,
                    'name' => 'Sara Khelifi',
                    'username' => 'skhelifi',
                    'hotel' => 'La Gazelle d\'Or',
                    'department' => __('Spa'),
                    'email' => 'sara.khelifi@hotel.dz',
                    'progress' => 45,
                    'status' => 'in_progress',
                    'lastLogin' => '02 Sep 2026',
                ],
                [
                    'id' => 3,
                    'rank' => 3,
                    'name' => 'Mourad Zitouni',
                    'username' => 'mzitouni',
                    'hotel' => 'La Gazelle d\'Or',
                    'department' => __('Housekeeping'),
                    'email' => 'mourad.zitouni@hotel.dz',
                    'progress' => 0,
                    'status' => 'not_started',
                    'lastLogin' => '-',
                ],
                [
                    'id' => 4,
                    'rank' => 4,
                    'name' => 'Khadija Amrani',
                    'username' => 'kamrani',
                    'hotel' => 'La Gazelle d\'Or',
                    'department' => __('Food Service'),
                    'email' => 'khadija.amrani@hotel.dz',
                    'progress' => 30,
                    'status' => 'in_progress',
                    'lastLogin' => '03 Sep 2026',
                ],
                [
                    'id' => 5,
                    'rank' => 5,
                    'name' => 'Yassine Djerbi',
                    'username' => 'ydjerbi',
                    'hotel' => 'La Gazelle d\'Or',
                    'department' => __('Kitchen'),
                    'email' => 'yassine.djerbi@hotel.dz',
                    'progress' => 15,
                    'status' => 'in_progress',
                    'lastLogin' => '01 Sep 2026',
                ],
                [
                    'id' => 6,
                    'rank' => 6,
                    'name' => 'Noura Saidi',
                    'username' => 'nsaidi',
                    'hotel' => 'La Gazelle d\'Or',
                    'department' => __('Reception'),
                    'email' => 'noura.saidi@hotel.dz',
                    'progress' => 76,
                    'status' => 'in_progress',
                    'lastLogin' => '05 Sep 2026',
                ],
                [
                    'id' => 7,
                    'rank' => 7,
                    'name' => 'Hakim Chenini',
                    'username' => 'hchenini',
                    'hotel' => 'La Gazelle d\'Or',
                    'department' => __('Technical Services'),
                    'email' => 'hakim.chenini@hotel.dz',
                    'progress' => 0,
                    'status' => 'not_started',
                    'lastLogin' => '-',
                ],
                [
                    'id' => 8,
                    'rank' => 8,
                    'name' => 'Fatima Laouar',
                    'username' => 'flaouar',
                    'hotel' => 'La Gazelle d\'Or',
                    'department' => __('Spa'),
                    'email' => 'fatima.laouar@hotel.dz',
                    'progress' => 100,
                    'status' => 'completed',
                    'lastLogin' => '04 Sep 2026',
                ],
                [
                    'id' => 9,
                    'rank' => 9,
                    'name' => 'Karim Boudiaf',
                    'username' => 'kboudiaf',
                    'hotel' => 'La Gazelle d\'Or',
                    'department' => __('Food Service'),
                    'email' => 'karim.boudiaf@hotel.dz',
                    'progress' => 60,
                    'status' => 'in_progress',
                    'lastLogin' => '02 Sep 2026',
                ],
                [
                    'id' => 10,
                    'rank' => 10,
                    'name' => 'Leila Brahimi',
                    'username' => 'lbrahimi',
                    'hotel' => 'La Gazelle d\'Or',
                    'department' => __('Housekeeping'),
                    'email' => 'leila.brahimi@hotel.dz',
                    'progress' => 0,
                    'status' => 'inactive',
                    'lastLogin' => '-',
                ],
            ],
            'pagination' => [
                'from' => 1,
                'to' => 10,
                'total' => 80,
                'currentPage' => 1,
                'lastPage' => 8,
                'pages' => [1, 2, 3, 4, 5, 'ellipsis', 8],
            ],
            'bulkActions' => [
                ['value' => 'select-action', 'label' => __('Select action')],
                ['value' => 'activate', 'label' => __('Activate selected')],
                ['value' => 'deactivate', 'label' => __('Deactivate selected')],
                ['value' => 'remind', 'label' => __('Send reminder')],
            ],
            'createForm' => [
                'hotels' => [
                    ['value' => 'la-gazelle-dor', 'label' => 'La Gazelle d\'Or'],
                    ['value' => 'aurassi', 'label' => __('Hotel El Aurassi')],
                    ['value' => 'sheraton-club', 'label' => __('Sheraton Club des Pins')],
                ],
                'departments' => [
                    ['value' => 'reception', 'label' => __('Reception')],
                    ['value' => 'spa', 'label' => __('Spa')],
                    ['value' => 'housekeeping', 'label' => __('Housekeeping')],
                    ['value' => 'food-service', 'label' => __('Food Service')],
                    ['value' => 'kitchen', 'label' => __('Kitchen')],
                ],
                'statuses' => [
                    ['value' => 'active', 'label' => __('Active')],
                    ['value' => 'inactive', 'label' => __('Inactive')],
                ],
                'defaultHotel' => 'la-gazelle-dor',
                'defaultDepartment' => 'reception',
                'defaultStatus' => 'active',
                'allowReminderEmails' => true,
            ],
        ]);
    }
}
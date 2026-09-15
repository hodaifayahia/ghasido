<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Super Admin hotels screen (ORG-01, SUB-01, SUB-03, SUB-04, SUB-06,
 * SUB-07, SUB-08, REP-01, ADM-02).
 *
 * Hotels and seat quotas are not modelled yet, so this page returns sample
 * data shaped to the frontend contract for a UI-first build.
 */
class HotelsController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/Hotels', [
            'stats' => [
                [
                    'key' => 'totalHotels',
                    'value' => 6,
                    'label' => __('Total Hotels'),
                ],
                [
                    'key' => 'activeContracts',
                    'value' => 2,
                    'label' => __('Active Contracts'),
                ],
                [
                    'key' => 'expiringSoon',
                    'value' => 2,
                    'label' => __('Expiring Soon'),
                    'detail' => __('Next 30 days'),
                ],
                [
                    'key' => 'pausedContracts',
                    'value' => 1,
                    'label' => __('Paused Access'),
                ],
                [
                    'key' => 'usedSeats',
                    'value' => 176,
                    'label' => __('Used Seats'),
                    'detail' => __('176 / 198 allocated'),
                ],
            ],
            'filters' => [
                'search' => '',
                'status' => 'all-statuses',
                'capacity' => 'all-capacities',
                'statuses' => [
                    ['value' => 'all-statuses', 'label' => __('All Statuses')],
                    ['value' => 'active', 'label' => __('Active')],
                    ['value' => 'expiring', 'label' => __('Expiring Soon')],
                    ['value' => 'paused', 'label' => __('Paused')],
                    ['value' => 'ended', 'label' => __('Ended')],
                ],
                'capacities' => [
                    [
                        'value' => 'all-capacities',
                        'label' => __('All Seat States'),
                    ],
                    ['value' => 'available', 'label' => __('Seats Available')],
                    ['value' => 'full', 'label' => __('At Capacity')],
                    ['value' => 'over', 'label' => __('Over Quota')],
                ],
            ],
            'hotels' => [
                [
                    'id' => 1,
                    'rank' => 1,
                    'name' => 'La Gazelle d\'Or',
                    'manager' => 'Meriem Haddad',
                    'city' => __('Algiers'),
                    'departments' => 7,
                    'usedSeats' => 58,
                    'totalSeats' => 64,
                    'contractEnd' => '03 Oct 2026',
                    'daysRemaining' => 18,
                    'status' => 'active',
                    'capacityState' => 'available',
                ],
                [
                    'id' => 2,
                    'rank' => 2,
                    'name' => __('Hotel El Aurassi'),
                    'manager' => 'Yacine Merabet',
                    'city' => __('Algiers'),
                    'departments' => 6,
                    'usedSeats' => 50,
                    'totalSeats' => 48,
                    'contractEnd' => '14 Oct 2026',
                    'daysRemaining' => 29,
                    'status' => 'active',
                    'capacityState' => 'over',
                ],
                [
                    'id' => 3,
                    'rank' => 3,
                    'name' => __('Sheraton Club des Pins'),
                    'manager' => 'Nabila Rahmani',
                    'city' => __('Staoueli'),
                    'departments' => 6,
                    'usedSeats' => 36,
                    'totalSeats' => 36,
                    'contractEnd' => '26 Sep 2026',
                    'daysRemaining' => 11,
                    'status' => 'expiring',
                    'capacityState' => 'full',
                ],
                [
                    'id' => 4,
                    'rank' => 4,
                    'name' => __('Azure Resort & Spa'),
                    'manager' => 'Sofiane Bensaid',
                    'city' => __('Oran'),
                    'departments' => 5,
                    'usedSeats' => 18,
                    'totalSeats' => 20,
                    'contractEnd' => '21 Sep 2026',
                    'daysRemaining' => 6,
                    'status' => 'expiring',
                    'capacityState' => 'available',
                ],
                [
                    'id' => 5,
                    'rank' => 5,
                    'name' => __('Desert Bloom Suites'),
                    'manager' => 'Imane Belkacem',
                    'city' => __('Ghardaia'),
                    'departments' => 4,
                    'usedSeats' => 14,
                    'totalSeats' => 18,
                    'contractEnd' => __('Paused'),
                    'daysRemaining' => null,
                    'status' => 'paused',
                    'capacityState' => 'available',
                ],
                [
                    'id' => 6,
                    'rank' => 6,
                    'name' => __('Sunrise Dunes Hotel'),
                    'manager' => 'Karima Ouali',
                    'city' => __('Biskra'),
                    'departments' => 4,
                    'usedSeats' => 0,
                    'totalSeats' => 12,
                    'contractEnd' => '31 Aug 2026',
                    'daysRemaining' => 0,
                    'status' => 'ended',
                    'capacityState' => 'available',
                ],
            ],
            'pagination' => [
                'from' => 1,
                'to' => 6,
                'total' => 6,
                'currentPage' => 1,
                'lastPage' => 1,
                'pages' => [1],
            ],
            'overview' => [
                'name' => 'La Gazelle d\'Or',
                'manager' => 'Meriem Haddad',
                'email' => 'meriem.haddad@guesvia.dz',
                'city' => __('Algiers'),
                'contractStart' => '05 Aug 2026',
                'contractEnd' => '03 Oct 2026',
                'daysRemaining' => 18,
                'usedSeats' => 58,
                'totalSeats' => 64,
                'employees' => 58,
                'departments' => 7,
                'status' => 'active',
                'alerts' => [
                    __('Renewal follow-up recommended within the next 3 weeks.'),
                    __('Reception and Food Service have reached their seat limits.'),
                ],
                'quotas' => [
                    [
                        'department' => __('Reception'),
                        'usedSeats' => 5,
                        'totalSeats' => 5,
                        'state' => 'full',
                    ],
                    [
                        'department' => __('Spa'),
                        'usedSeats' => 7,
                        'totalSeats' => 8,
                        'state' => 'available',
                    ],
                    [
                        'department' => __('Housekeeping'),
                        'usedSeats' => 10,
                        'totalSeats' => 12,
                        'state' => 'available',
                    ],
                    [
                        'department' => __('Food Service'),
                        'usedSeats' => 12,
                        'totalSeats' => 12,
                        'state' => 'full',
                    ],
                    [
                        'department' => __('Kitchen'),
                        'usedSeats' => 9,
                        'totalSeats' => 10,
                        'state' => 'available',
                    ],
                    [
                        'department' => __('Marketing'),
                        'usedSeats' => 7,
                        'totalSeats' => 6,
                        'state' => 'over',
                    ],
                    [
                        'department' => __('Technical Services'),
                        'usedSeats' => 8,
                        'totalSeats' => 11,
                        'state' => 'available',
                    ],
                ],
            ],
        ]);
    }
}
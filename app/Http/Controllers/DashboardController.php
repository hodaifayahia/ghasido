<?php

namespace App\Http\Controllers;

use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Super Admin dashboard (ADM-01, REP-01).
 *
 * Hotels, departments, enrolments and attempts have no models yet, so every
 * figure below is the sample data from the Admin Dashboard mockup. The prop
 * shapes are the real contract (resources/js/types/dashboard.ts): swap each
 * method body for a query once the domain models land, and the page keeps
 * working unchanged.
 */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $departments = $this->departments();
        $training = $this->sumBreakdowns(array_column($departments, 'breakdown'));
        $employees = array_sum($training);
        $started = $training['completed'] + $training['inProgress'];

        return Inertia::render('Dashboard', [
            'stats' => [
                ['key' => 'hotels', 'value' => 1, 'label' => __('Hotel'), 'detail' => 'La Gazelle d’Or'],
                ['key' => 'departments', 'value' => count($departments), 'label' => __('Departments'), 'detail' => __('Active')],
                ['key' => 'employees', 'value' => $employees, 'label' => __('Employees'), 'detail' => __('Total accounts')],
                ['key' => 'trainingStarted', 'value' => $started, 'label' => __('Started Training'), 'detail' => $this->percentOf($started, $employees)],
                ['key' => 'trainingCompleted', 'value' => $training['completed'], 'label' => __('Completed Training'), 'detail' => $this->percentOf($training['completed'], $employees)],
                ['key' => 'averageProgress', 'value' => 54, 'unit' => '%', 'label' => __('Average Progress')],
            ],
            'trainingOverview' => [
                'all' => $training,
                'departments' => $departments,
            ],
            'departmentProgress' => array_map(
                fn (array $department): array => [
                    'id' => $department['id'],
                    'name' => $department['name'],
                    'percent' => $department['percent'],
                ],
                $departments,
            ),
            'needsAttention' => $this->needsAttention(),
            'recentActivity' => $this->recentActivity(),
        ]);
    }

    /**
     * @return list<array{id: int, name: string, percent: int, breakdown: array{completed: int, inProgress: int, notStarted: int}}>
     */
    private function departments(): array
    {
        $rows = [
            // name, progress %, completed, in progress, not started
            ['Reception', 67, 7, 8, 1],
            ['Spa', 44, 3, 5, 2],
            ['Housekeeping', 50, 4, 13, 1],
            ['Food Service', 49, 2, 10, 2],
            ['Kitchen', 38, 2, 8, 2],
            ['Marketing', 0, 0, 0, 5],
            ['Technical Services', 0, 0, 0, 5],
        ];

        return array_map(
            fn (array $row, int $index): array => [
                'id' => $index + 1,
                'name' => $row[0],
                'percent' => $row[1],
                'breakdown' => [
                    'completed' => $row[2],
                    'inProgress' => $row[3],
                    'notStarted' => $row[4],
                ],
            ],
            $rows,
            array_keys($rows),
        );
    }

    /**
     * @param  list<array{completed: int, inProgress: int, notStarted: int}>  $breakdowns
     * @return array{completed: int, inProgress: int, notStarted: int}
     */
    private function sumBreakdowns(array $breakdowns): array
    {
        return [
            'completed' => array_sum(array_column($breakdowns, 'completed')),
            'inProgress' => array_sum(array_column($breakdowns, 'inProgress')),
            'notStarted' => array_sum(array_column($breakdowns, 'notStarted')),
        ];
    }

    private function percentOf(int $part, int $whole): string
    {
        return $whole === 0 ? '0%' : round($part / $whole * 100, 1).'%';
    }

    /**
     * @return list<array{key: string, label: string, total: int, employees: list<array{id: int, name: string, department: string, lastLogin: string}>}>
     */
    private function needsAttention(): array
    {
        $groups = [
            'inactive' => [__('Inactive Employees'), 8, [
                ['Ali Ben Salem', 'Reception', 5],
                ['Sara Khelifi', 'Spa', 6],
                ['Mourad Zitouni', 'Housekeeping', 8],
                ['Khadija Amrani', 'Food Service', 10],
                ['Yassine Djerbi', 'Kitchen', 7],
            ]],
            'notStarted' => [__('Not Started'), 18, [
                ['Lina Bouzid', 'Marketing', 2],
                ['Rachid Mansouri', 'Technical Services', 3],
                ['Nadia Hamdi', 'Marketing', 1],
                ['Omar Belkacem', 'Technical Services', 4],
                ['Samira Touati', 'Reception', 2],
            ]],
            'pretestFinished' => [__('Pre-test Finished'), 12, [
                ['Walid Ferhat', 'Kitchen', 1],
                ['Imane Saadi', 'Spa', 2],
                ['Karim Haddad', 'Food Service', 3],
                ['Leila Mebarki', 'Housekeeping', 1],
                ['Sofiane Brahimi', 'Reception', 4],
            ]],
        ];

        $id = 0;
        $result = [];

        foreach ($groups as $key => [$label, $total, $employees]) {
            $result[] = [
                'key' => $key,
                'label' => $label,
                'total' => $total,
                'employees' => array_map(
                    function (array $employee) use (&$id): array {
                        return [
                            'id' => ++$id,
                            'name' => $employee[0],
                            'department' => $employee[1],
                            'lastLogin' => Carbon::now()->subDays($employee[2])->diffForHumans(),
                        ];
                    },
                    $employees,
                ),
            ];
        }

        return $result;
    }

    /**
     * @return list<array{id: int, date: string, time: string, employee: string, type: string, activity: string, details: string}>
     */
    private function recentActivity(): array
    {
        $rows = [
            ['2026-09-07 14:32', 'Amine Ben Ali', 'lessonCompleted', __('Completed a lesson'), 'Lesson 3 – At the Restaurant'],
            ['2026-09-07 12:15', 'Noura Saidi', 'pretestFinished', __('Finished Pre-test'), 'Score: 76% (19/25)'],
            ['2026-09-07 10:48', 'Karim Boudiaf', 'roleplayUsed', __('Used AI Role-play'), 'Scenario: Handling a complaint'],
            ['2026-09-06 16:20', 'Fatima Laouar', 'trainingCompleted', __('Completed Training'), '100% – All lessons'],
            ['2026-09-06 11:05', 'Hakim Chenini', 'trainingStarted', __('Started Training'), 'Lesson 1 – Welcoming Guests'],
        ];

        return array_map(
            fn (array $row, int $index): array => [
                'id' => $index + 1,
                'date' => Carbon::parse($row[0])->format('d M Y'),
                'time' => Carbon::parse($row[0])->format('H:i'),
                'employee' => $row[1],
                'type' => $row[2],
                'activity' => $row[3],
                'details' => $row[4],
            ],
            $rows,
            array_keys($rows),
        );
    }
}

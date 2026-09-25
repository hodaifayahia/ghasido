<?php

namespace App\Services\Reports;

use App\Enums\Permission;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\User;
use App\Services\Reports\Exporters\DatasetExport;
use Illuminate\Database\Eloquent\Builder;

/**
 * The read side of Reports & Export: filters in, page props out
 * (REP-01..04, spec 0003 Part D).
 *
 * The controller only calls this. The props keep the shapes the approved
 * components read (resources/js/types/reports.ts), extended, never renamed.
 */
final class ReportPage
{
    public function __construct(private readonly ReportFilters $filters, private readonly User $viewer) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $population = new ReportPopulation($this->filters);
        $aggregates = new ReportAggregates($this->filters, $population);
        $rows = new ReportRows($this->filters, $population, $this->viewer);

        return [
            'filters' => $this->filterProps(),
            'stats' => $aggregates->stats(),
            'prePost' => $aggregates->prePost(),
            'completion' => $aggregates->completion(),
            'aiPerformance' => $aggregates->aiPerformance(),
            'activity' => $aggregates->activity(),
            'tabs' => self::tabs(),
            'activeTab' => $this->filters->tab,
            ...$rows->build(),
            'search' => $this->filters->search,
            'includeDetailedAnswers' => true,
            'exportActions' => self::exportActions(),
            'datasets' => $this->datasets(),
            'detail' => (new EmployeeDetail($population))->build($this->filters->detailId),
            'canViewTranscripts' => $rows->canViewTranscripts(),
            'canExport' => $this->viewer->can(Permission::ReportsExport->value),
            'canExportAnonymised' => $this->viewer->can(Permission::ReportsExportAnonymised->value),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filterProps(): array
    {
        $filters = $this->filters;

        return [
            'range' => $filters->range,
            'from' => $filters->from->toDateString(),
            'to' => $filters->to->toDateString(),
            'rangeLabel' => $filters->rangeLabel(),
            'hotel' => $filters->hotelId === null ? ReportFilters::ALL_HOTELS : (string) $filters->hotelId,
            'department' => $filters->departmentId === null ? ReportFilters::ALL_DEPARTMENTS : (string) $filters->departmentId,
            'employee' => $filters->employeeId === null ? ReportFilters::ALL_EMPLOYEES : (string) $filters->employeeId,
            'activityType' => $filters->activityType,
            'completionStatus' => $filters->completionStatus,
            'ranges' => $filters->rangeOptions(),
            'hotels' => $this->hotelOptions(),
            'departments' => $this->departmentOptions(),
            'employees' => $this->employeeOptions(),
            'activityTypes' => [
                ['value' => ReportFilters::ALL_ACTIVITIES, 'label' => __('All Activities')],
                ['value' => ReportFilters::ACTIVITY_LESSONS, 'label' => __('Lessons')],
                ['value' => ReportFilters::ACTIVITY_TESTS, 'label' => __('Pre/Post Tests')],
                ['value' => ReportFilters::ACTIVITY_ROLEPLAY, 'label' => __('AI Scenarios')],
            ],
            'completionStatuses' => [
                ['value' => ReportFilters::ALL_STATUSES, 'label' => __('All')],
                ['value' => ReportFilters::STATUS_ACTIVE, 'label' => __('Active')],
                ['value' => ReportFilters::STATUS_IN_PROGRESS, 'label' => __('In progress')],
                ['value' => ReportFilters::STATUS_COMPLETED, 'label' => __('Completed')],
                ['value' => ReportFilters::STATUS_INACTIVE, 'label' => __('Inactive')],
            ],
        ];
    }

    /**
     * The Super Admin picks any hotel; a manager's list holds only their own
     * (ROLE-02). The tenant scope on Hotel already narrows the query for a
     * manager; the explicit where keeps the intent visible.
     *
     * @return list<array{value: string, label: string}>
     */
    private function hotelOptions(): array
    {
        $query = Hotel::query()->notArchived()->orderBy('name');
        $scoped = ReportFilters::scopedHotelId($this->viewer);

        if ($scoped !== null) {
            $query->whereKey($scoped);
        }

        $options = $scoped === null
            ? [['value' => ReportFilters::ALL_HOTELS, 'label' => __('All Hotels')]]
            : [];

        foreach ($query->get(['id', 'name']) as $hotel) {
            $options[] = ['value' => (string) $hotel->id, 'label' => $hotel->name];
        }

        return $options;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function departmentOptions(): array
    {
        $query = Department::query()->orderBy('position')->orderBy('name');
        $hotelId = $this->filters->hotelId;

        if ($hotelId !== null) {
            $query->where(function (Builder $scope) use ($hotelId): void {
                $scope->whereNull('hotel_id')->orWhere('hotel_id', $hotelId);
            });
        }

        $options = [['value' => ReportFilters::ALL_DEPARTMENTS, 'label' => __('All Departments')]];

        foreach ($query->get(['id', 'name']) as $department) {
            $options[] = ['value' => (string) $department->id, 'label' => $department->name];
        }

        return $options;
    }

    /**
     * The employees of the filtered hotel and department, so the select
     * never lists someone the other filters would exclude.
     *
     * @return list<array{value: string, label: string}>
     */
    private function employeeOptions(): array
    {
        $query = User::query()->employees()->orderBy('name')->orderBy('id');

        if ($this->filters->hotelId !== null) {
            $query->where('hotel_id', $this->filters->hotelId);
        }

        if ($this->filters->departmentId !== null) {
            $query->where('department_id', $this->filters->departmentId);
        }

        $options = [['value' => ReportFilters::ALL_EMPLOYEES, 'label' => __('All Employees')]];

        foreach ($query->get(['id', 'name']) as $employee) {
            $options[] = ['value' => (string) $employee->id, 'label' => $employee->name];
        }

        return $options;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function tabs(): array
    {
        return [
            ['key' => ReportFilters::TAB_EMPLOYEES, 'label' => __('Employee Results')],
            ['key' => ReportFilters::TAB_ANSWERS, 'label' => __('Detailed Answers')],
            ['key' => ReportFilters::TAB_ROLEPLAY, 'label' => __('AI Role-play Logs')],
            ['key' => ReportFilters::TAB_LESSONS, 'label' => __('Lesson Progress')],
            ['key' => ReportFilters::TAB_COMPARISON, 'label' => __('Pre/Post Comparison')],
            ['key' => ReportFilters::TAB_DOWNLOADS, 'label' => __('Download Center')],
        ];
    }

    /**
     * The three buttons under the table: the same dataset, three formats.
     *
     * @return list<array{id: string, label: string, tone: string}>
     */
    public static function exportActions(): array
    {
        return [
            ['id' => DatasetExport::FORMAT_XLSX, 'label' => __('Export to Excel (.xlsx)'), 'tone' => 'excel'],
            ['id' => DatasetExport::FORMAT_CSV, 'label' => __('Export to CSV (.csv)'), 'tone' => 'brand'],
            ['id' => DatasetExport::FORMAT_PDF, 'label' => __('Export PDF Report'), 'tone' => 'danger'],
        ];
    }

    /**
     * The Download Center's datasets. The anonymised export is listed only
     * for a viewer who may run it (REP-07).
     *
     * @return list<array{key: string, label: string, description: string, anonymised: bool}>
     */
    private function datasets(): array
    {
        $datasets = [
            [
                'key' => DatasetExport::DATASET_EMPLOYEES,
                'label' => __('Employee Results'),
                'description' => __('One row per employee: scores, lessons, AI scenarios, activity and status.'),
                'anonymised' => false,
            ],
            [
                'key' => DatasetExport::DATASET_ANSWERS,
                'label' => __('Detailed Answers'),
                'description' => __('Long format, one row per answer: question, raw answer, correctness, score, AI criteria, time taken and question version.'),
                'anonymised' => false,
            ],
            [
                'key' => DatasetExport::DATASET_ROLEPLAY,
                'label' => __('AI Role-play Logs'),
                'description' => $this->viewer->can(Permission::TranscriptsView->value)
                    ? __('One row per attempt with criteria scores, feedback summary and the full transcript.')
                    : __('One row per attempt with criteria scores and the feedback summary.'),
                'anonymised' => false,
            ],
            [
                'key' => DatasetExport::DATASET_LESSONS,
                'label' => __('Lesson Progress'),
                'description' => __('One row per employee per completed lesson.'),
                'anonymised' => false,
            ],
            [
                'key' => DatasetExport::DATASET_COMPARISON,
                'label' => __('Pre/Post Comparison'),
                'description' => __('One row per employee per skill: Pre-test and Post-test results side by side.'),
                'anonymised' => false,
            ],
        ];

        if ($this->viewer->can(Permission::ReportsExportAnonymised->value)) {
            $datasets[] = [
                'key' => DatasetExport::DATASET_ANONYMISED,
                'label' => __('Anonymised Research Export'),
                'description' => __('Detailed answers keyed on the participant code only: no name, username or email.'),
                'anonymised' => true,
            ];
        }

        return $datasets;
    }
}

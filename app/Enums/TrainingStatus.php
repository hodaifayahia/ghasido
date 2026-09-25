<?php

namespace App\Enums;

/**
 * The status pill of an employee row on Manage Employees (PROG-02, DATA-07).
 *
 * Never stored: derived on the way out from the account status, the two
 * training timestamps and the lesson completions, in SQL and in PHP by
 * App\Services\Employees\EmployeeDirectory so the pill, the filter and the
 * stat cards can never disagree. Matches EmployeeStatus in
 * resources/js/types/employees.ts.
 */
enum TrainingStatus: string
{
    case Completed = 'completed';
    case InProgress = 'in_progress';
    case NotStarted = 'not_started';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Completed => __('Completed'),
            self::InProgress => __('In Progress'),
            self::NotStarted => __('Not Started'),
            self::Inactive => __('Inactive'),
        };
    }
}

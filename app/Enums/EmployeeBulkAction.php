<?php

namespace App\Enums;

/**
 * What the Bulk Actions panel of Manage Employees may do to the checked rows
 * (AUTH-08, REM-07; spec 0003 Part D, employees.bulk).
 */
enum EmployeeBulkAction: string
{
    case Activate = 'activate';
    case Deactivate = 'deactivate';
    case Remind = 'remind';

    public function label(): string
    {
        return match ($this) {
            self::Activate => __('Activate selected'),
            self::Deactivate => __('Deactivate selected'),
            self::Remind => __('Send reminder'),
        };
    }
}

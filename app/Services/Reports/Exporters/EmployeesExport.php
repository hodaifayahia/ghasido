<?php

namespace App\Services\Reports\Exporters;

use App\Models\User;
use App\Services\Reports\ReportPopulation;

/**
 * One row per employee: the Employee Results tab as a sheet (REP-03,
 * REP-04, DATA-06, DATA-07).
 */
final class EmployeesExport extends DatasetExport
{
    public function key(): string
    {
        return self::DATASET_EMPLOYEES;
    }

    public function title(): string
    {
        return __('Employee Results');
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return [
            'Participant code', 'Name', 'Username', 'Email', 'Hotel', 'Department', 'Account status', 'Report status',
            'Pre-test %', 'Post-test %', 'Lessons completed', 'Lessons total', 'AI scenarios completed', 'AI scenarios total',
            'Last activity', 'Training started', 'Training completed', 'Reminder consent',
        ];
    }

    /**
     * @return iterable<int, list<mixed>>
     */
    public function rows(): iterable
    {
        $query = $this->population->searched()
            ->with(['hotel:id,name', 'department:id,name'])
            ->reorder();

        /** @var User $user */
        foreach ($query->lazyById(self::CHUNK, 'users.id', 'id') as $user) {
            $figures = ReportPopulation::figures($user);

            yield [
                $user->participant_code,
                $user->name,
                $user->username,
                $user->email,
                $user->hotel?->name,
                $user->department?->name,
                $user->status->value,
                ReportPopulation::statusOf($user),
                $figures['preScore'],
                $figures['postScore'],
                $figures['lessonsCompleted'],
                $figures['lessonsTotal'],
                $figures['scenariosCompleted'],
                $figures['scenariosTotal'],
                $user->last_activity_at,
                $user->training_started_at,
                $user->training_completed_at,
                $user->email_consent_at,
            ];
        }
    }

    public function count(): int
    {
        return $this->population->searched()->count();
    }
}

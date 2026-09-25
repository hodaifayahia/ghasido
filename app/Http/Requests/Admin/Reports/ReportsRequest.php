<?php

namespace App\Http\Requests\Admin\Reports;

use App\Enums\Permission;
use App\Models\User;
use App\Services\Reports\ReportFilters;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Reports & Export page (REP-01, REP-02, ROLE-02).
 *
 * Authorization answers two questions: may this user read reports at all,
 * and do the parameters stay inside their hotel. A manager naming another
 * hotel, or another hotel's employee, is refused with a 403 here, never
 * quietly shown an empty page.
 */
class ReportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->can(Permission::ReportsView->value)
            && ReportFilters::viewerMayUse($this, $user);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'range' => ['nullable', 'string', 'max:32'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'hotel' => ['nullable', 'string', 'max:32'],
            'department' => ['nullable', 'string', 'max:32'],
            'employee' => ['nullable', 'string', 'max:32'],
            'activityType' => ['nullable', 'string', 'max:32'],
            'completionStatus' => ['nullable', 'string', 'max:32'],
            'tab' => ['nullable', 'string', 'max:32'],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer'],
            'detail' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function filters(): ReportFilters
    {
        /** @var User $user */
        $user = $this->user();

        return ReportFilters::fromRequest($this, $user);
    }

    public function viewer(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }
}

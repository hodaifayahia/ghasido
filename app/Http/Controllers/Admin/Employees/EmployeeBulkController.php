<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Enums\EmployeeBulkAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Employees\BulkEmployeesRequest;
use App\Models\User;
use App\Services\Employees\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Bulk Actions on the checked rows (AUTH-08, REM-07; spec 0003 Part D,
 * employees.bulk).
 */
class EmployeeBulkController extends Controller
{
    public function apply(BulkEmployeesRequest $request, EmployeeService $employees): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        /** @var EmployeeBulkAction $action */
        $action = $request->action();
        $targets = $request->employees();

        $result = $employees->bulk($action, $targets, $actor);

        Inertia::flash('toast', [
            'type' => $result['changed'] === 0 ? 'info' : 'success',
            'message' => $this->summary($action, $result),
        ]);

        return back();
    }

    /**
     * @param  array{changed: int, skipped: int, blocked: int}  $result
     */
    private function summary(EmployeeBulkAction $action, array $result): string
    {
        return match ($action) {
            EmployeeBulkAction::Activate => trim(
                __(':count activated.', ['count' => $result['changed']])
                .($result['skipped'] > 0 ? ' '.__(':count skipped: seat limit reached.', ['count' => $result['skipped']]) : ''),
            ),
            EmployeeBulkAction::Deactivate => __(':count deactivated. Nothing was deleted.', ['count' => $result['changed']]),
            EmployeeBulkAction::Remind => trim(
                __('Reminder sent to :count.', ['count' => $result['changed']])
                .($result['blocked'] > 0 ? ' '.__(':count blocked: no consent or no email.', ['count' => $result['blocked']]) : ''),
            ),
        };
    }
}

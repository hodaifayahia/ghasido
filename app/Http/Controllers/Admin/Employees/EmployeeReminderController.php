<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Employees\RemindEmployeesRequest;
use App\Models\ReminderTemplate;
use App\Models\User;
use App\Services\Employees\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Send Reminder: one row's mail button or the panel for the checked rows
 * (REM-02, REM-05, REM-07; spec 0003 Part D, employees.remind).
 *
 * The toast says how many went out and how many were blocked for want of
 * consent or an address; the blocked rows are kept as reminders too, so the
 * Messages screen shows them (REM-06).
 */
class EmployeeReminderController extends Controller
{
    public function send(RemindEmployeesRequest $request, EmployeeService $employees): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $templateId = $request->templateId();
        $template = $templateId === null ? null : ReminderTemplate::query()->findOrFail($templateId);

        $result = $employees->remind($request->employees(), $actor, $template);

        $message = __('“:template” sent to :count employee(s).', [
            'template' => $result['template'],
            'count' => $result['queued'],
        ]);

        if ($result['blocked'] > 0) {
            $message .= ' '.__(':count blocked: no consent or no email address.', ['count' => $result['blocked']]);
        }

        Inertia::flash('toast', [
            'type' => $result['queued'] === 0 ? 'warning' : 'success',
            'message' => $message,
        ]);

        return back();
    }
}

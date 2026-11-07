<?php

namespace App\Http\Controllers\Admin\Messages;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use App\Services\Reminders\ReminderLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * The reminder log: delete one entry (REM-06, SEC-06).
 */
class ReminderLogController extends Controller
{
    public function destroy(Reminder $reminder, ReminderLogService $log): RedirectResponse
    {
        // A manager deletes only rows of their own hotel's employees; the
        // Super Admin any row (ReminderPolicy::delete, ROLE-02).
        Gate::authorize('delete', $reminder);

        // A learner's answer is kept with its row, for the Super Admin too,
        // whom Gate::before lets past the policy (DATA-10, client request
        // 2026-11-07).
        if ($reminder->reply()->exists()) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('This reminder has a reply from the employee, so it is kept.'),
            ]);

            return back();
        }

        $log->delete($reminder);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('The reminder log entry was deleted.'),
        ]);

        return back();
    }
}

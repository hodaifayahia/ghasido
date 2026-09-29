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

        $log->delete($reminder);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('The reminder log entry was deleted.'),
        ]);

        return back();
    }
}

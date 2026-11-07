<?php

namespace App\Http\Controllers\Admin\Messages;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use App\Models\User;
use App\Services\Reminders\ReminderReplyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Mark the replies to one message read (client request 2026-11-07). The
 * service touches only the replies this admin may see, so a manager can
 * never clear another hotel's badge (REM-07, ROLE-02).
 */
class ReminderReplyController extends Controller
{
    public function read(Request $request, string $batch, ReminderReplyService $replies): RedirectResponse
    {
        Gate::authorize('viewAny', Reminder::class);

        /** @var User $actor */
        $actor = $request->user('web');

        $replies->markBatchRead($actor, $batch);

        return back();
    }
}

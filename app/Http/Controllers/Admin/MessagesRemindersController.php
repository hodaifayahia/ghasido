<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use App\Models\User;
use App\Services\Reminders\MessagesDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Messages & Reminders screen (REM-01, REM-02, REM-03, REM-04, REM-05,
 * REM-06, REM-07, REP-01, ADM-02; spec 0003 Part D).
 *
 * Read → authorize → delegate. No query is written here: MessagesDirectory
 * reads through RecipientQuery and the models, the form requests under
 * Admin\Messages validate every write, and the services perform them with
 * their audit rows.
 */
class MessagesRemindersController extends Controller
{
    public function __invoke(Request $request, MessagesDirectory $directory): Response
    {
        // The route already carries permission:messages.view. The policy
        // check is the authorization proper, so it holds even if the route's
        // middleware is ever changed (ROLE-01, SEC-01).
        Gate::authorize('viewAny', Reminder::class);

        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('admin/MessagesReminders', $directory->build($request, $actor));
    }
}

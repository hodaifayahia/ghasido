<?php

namespace App\Http\Controllers\Admin\Messages;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Messages\SendReminderRequest;
use App\Models\User;
use App\Services\Reminders\ReminderBatchService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Send Reminder, now or scheduled (REM-01, REM-02, REM-05, REM-07; spec 0003
 * Part D). Read → authorize (the form request) → delegate → toast.
 */
class ReminderSendController extends Controller
{
    public function store(SendReminderRequest $request, ReminderBatchService $batch): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $scheduledFor = $request->scheduledFor();

        $summary = $batch->send(
            $actor,
            $request->recipientIds(),
            $request->selectAll(),
            $request->filters(),
            $request->template(),
            $request->channel(),
            $scheduledFor,
        );

        $parts = [];

        if ($scheduledFor !== null) {
            $parts[] = __('Scheduled for :count on :date', [
                'count' => $summary['scheduled'],
                'date' => $scheduledFor->format('d M Y H:i'),
            ]);
        } else {
            $parts[] = __('Sent to :count', ['count' => $summary['delivered']]);
        }

        if ($summary['blocked'] > 0) {
            $parts[] = __('blocked :count (no consent)', ['count' => $summary['blocked']]);
        }

        if ($summary['skipped'] > 0) {
            $parts[] = __('skipped :count (deactivated)', ['count' => $summary['skipped']]);
        }

        Inertia::flash('toast', [
            'type' => $summary['delivered'] + $summary['scheduled'] > 0 ? 'success' : 'warning',
            'message' => implode(', ', $parts).'.',
        ]);

        return back();
    }
}

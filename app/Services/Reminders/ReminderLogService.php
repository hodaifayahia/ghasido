<?php

namespace App\Services\Reminders;

use App\Models\AuditLog;
use App\Models\Reminder;
use Illuminate\Support\Facades\DB;

/**
 * Removing one row from the reminder log (REM-06, SEC-06).
 *
 * Only the row asked for goes: the template, the rule and the employee are
 * untouched, and the audit row keeps what the deleted entry said, so the
 * removal itself stays traceable. A queued or scheduled row that is deleted
 * is simply never sent: SendReminderEmail skips a row that no longer exists.
 */
class ReminderLogService
{
    public function delete(Reminder $reminder): void
    {
        DB::transaction(function () use ($reminder): void {
            AuditLog::record($reminder, 'reminder.deleted', [
                'deleted' => [
                    'user_id' => $reminder->user_id,
                    'template_id' => $reminder->template_id,
                    'automation_rule_id' => $reminder->automation_rule_id,
                    'channel' => $reminder->channel->value,
                    'subject' => $reminder->subject,
                    'status' => $reminder->status->value,
                    'sent_at' => $reminder->sent_at?->toIso8601String(),
                    'scheduled_for' => $reminder->scheduled_for?->toIso8601String(),
                ],
            ]);

            $reminder->delete();
        });
    }
}

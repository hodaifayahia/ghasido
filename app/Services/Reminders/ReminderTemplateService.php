<?php

namespace App\Services\Reminders;

use App\Models\AuditLog;
use App\Models\ReminderTemplate;
use Illuminate\Support\Facades\DB;

/**
 * Every write to a reminder template (REM-04, SEC-06; spec 0003 Part D).
 *
 * The form request validates; this class changes the row and writes exactly
 * one audit row inside one transaction, so an edit cannot reach the database
 * without its trace (spec 0003 Part A rule 2).
 *
 * Editing a template never rewrites what was already sent: each Reminder row
 * carries the text as rendered at send time.
 */
class ReminderTemplateService
{
    /**
     * @param  array{name: string, subject: string, body: string, audience_label: string|null, trigger_label: string|null, is_active: bool}  $data
     */
    public function create(array $data): ReminderTemplate
    {
        return DB::transaction(function () use ($data): ReminderTemplate {
            $template = new ReminderTemplate;
            $template->fill($data);
            $template->save();

            AuditLog::record($template, 'reminder_template.created', [
                'created' => $template->only(['name', 'subject', 'audience_label', 'trigger_label', 'is_active']),
            ]);

            return $template;
        });
    }

    /**
     * @param  array{name: string, subject: string, body: string, audience_label: string|null, trigger_label: string|null, is_active: bool}  $data
     */
    public function update(ReminderTemplate $template, array $data): ReminderTemplate
    {
        return DB::transaction(function () use ($template, $data): ReminderTemplate {
            $template->fill($data);

            // Recorded before save() so the attribute diff is still readable.
            AuditLog::record($template, 'reminder_template.updated');

            $template->save();

            return $template;
        });
    }
}

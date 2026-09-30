<?php

namespace App\Services\Reminders;

use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\ReminderTemplate;
use Illuminate\Support\Facades\DB;
use LogicException;

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

    /**
     * The names of the automation rules that still send this template.
     *
     * A rule cannot live without its template (`automation_rules.template_id`
     * is required and cascades on delete), so a template in use is never
     * deleted: the admin points those rules at another template, or deletes
     * them, first. Nothing is detached silently.
     *
     * @return list<string>
     */
    public function rulesUsing(ReminderTemplate $template): array
    {
        /** @var list<string> $names */
        $names = $template->automationRules()
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->values()
            ->all();

        return $names;
    }

    /**
     * Delete a template no rule uses, with its audit row.
     *
     * The reminders already sent with it keep their rendered subject and body
     * (`reminders.template_id` is set to null), so the log still reads what
     * each employee received (REM-06).
     *
     * @throws LogicException when a rule still uses the template
     */
    public function delete(ReminderTemplate $template): void
    {
        DB::transaction(function () use ($template): void {
            // Re-checked under a lock, so a rule saved a moment ago is not
            // cascaded away with the template.
            $inUse = AutomationRule::query()
                ->where('template_id', $template->id)
                ->lockForUpdate()
                ->exists();

            if ($inUse) {
                throw new LogicException('The reminder template is still used by an automation rule.');
            }

            AuditLog::record($template, 'reminder_template.deleted', [
                'deleted' => $template->only(['name', 'subject', 'audience_label', 'trigger_label', 'is_active']),
            ]);

            $template->delete();
        });
    }
}

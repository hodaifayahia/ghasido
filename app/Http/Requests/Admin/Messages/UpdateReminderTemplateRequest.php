<?php

namespace App\Http\Requests\Admin\Messages;

use App\Models\ReminderTemplate;

/**
 * Edit Template (REM-04). Super Admin only, through ReminderTemplatePolicy.
 */
class UpdateReminderTemplateRequest extends ReminderTemplateRequest
{
    public function authorize(): bool
    {
        $template = $this->route('template');

        return $template instanceof ReminderTemplate
            && ($this->user()?->can('update', $template) ?? false);
    }
}

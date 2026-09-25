<?php

namespace App\Http\Requests\Admin\Messages;

use App\Models\ReminderTemplate;

/**
 * Add Template (REM-04). Super Admin only, through ReminderTemplatePolicy.
 */
class StoreReminderTemplateRequest extends ReminderTemplateRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ReminderTemplate::class) ?? false;
    }
}

<?php

namespace App\Http\Requests\Admin\Messages;

use App\Models\AutomationRule;

/**
 * Add Rule (REM-03). Super Admin only, through AutomationRulePolicy.
 */
class StoreAutomationRuleRequest extends AutomationRuleRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AutomationRule::class) ?? false;
    }
}

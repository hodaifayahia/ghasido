<?php

namespace App\Http\Requests\Admin\Messages;

use App\Models\AutomationRule;

/**
 * Edit Rule (REM-03). Super Admin only, through AutomationRulePolicy.
 */
class UpdateAutomationRuleRequest extends AutomationRuleRequest
{
    public function authorize(): bool
    {
        $rule = $this->route('rule');

        return $rule instanceof AutomationRule
            && ($this->user()?->can('update', $rule) ?? false);
    }
}

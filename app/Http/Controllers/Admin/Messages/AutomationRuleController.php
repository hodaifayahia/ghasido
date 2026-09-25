<?php

namespace App\Http\Controllers\Admin\Messages;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Messages\StoreAutomationRuleRequest;
use App\Http\Requests\Admin\Messages\UpdateAutomationRuleRequest;
use App\Models\AutomationRule;
use App\Services\Reminders\AutomationRuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Automation rules: add, edit, switch on or off (REM-03; spec 0003 Part D).
 */
class AutomationRuleController extends Controller
{
    public function store(StoreAutomationRuleRequest $request, AutomationRuleService $rules): RedirectResponse
    {
        $rule = $rules->create($request->ruleData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':rule was added.', ['rule' => $rule->name]),
        ]);

        return back();
    }

    public function update(UpdateAutomationRuleRequest $request, AutomationRule $rule, AutomationRuleService $rules): RedirectResponse
    {
        $rules->update($rule, $request->ruleData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':rule was updated.', ['rule' => $rule->name]),
        ]);

        return back();
    }

    public function toggle(AutomationRule $rule, AutomationRuleService $rules): RedirectResponse
    {
        Gate::authorize('toggle', $rule);

        $rules->toggle($rule);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $rule->is_active
                ? __(':rule is active.', ['rule' => $rule->name])
                : __(':rule is paused.', ['rule' => $rule->name]),
        ]);

        return back();
    }
}

<?php

namespace App\Services\Reminders;

use App\Enums\AutomationTrigger;
use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\Department;
use App\Models\Hotel;
use Illuminate\Support\Facades\DB;

/**
 * Every write to an automation rule (REM-03, SEC-06; spec 0003 Part D).
 *
 * The audience is stored as `{hotel_ids, department_ids}` for the runner and
 * summarised into `audience_label` here, so the card on the Messages screen
 * and the runner can never describe two different audiences.
 */
class AutomationRuleService
{
    /**
     * @param  array{name: string, trigger: AutomationTrigger, days: int|null, template_id: int, hotel_ids: list<int>, department_ids: list<int>, is_active: bool}  $data
     */
    public function create(array $data): AutomationRule
    {
        return DB::transaction(function () use ($data): AutomationRule {
            $rule = new AutomationRule;
            $this->apply($rule, $data);
            $rule->save();

            AuditLog::record($rule, 'automation_rule.created', [
                'created' => $rule->only(['name', 'trigger', 'days', 'template_id', 'audience', 'is_active']),
            ]);

            return $rule;
        });
    }

    /**
     * @param  array{name: string, trigger: AutomationTrigger, days: int|null, template_id: int, hotel_ids: list<int>, department_ids: list<int>, is_active: bool}  $data
     */
    public function update(AutomationRule $rule, array $data): AutomationRule
    {
        return DB::transaction(function () use ($rule, $data): AutomationRule {
            $this->apply($rule, $data);

            AuditLog::record($rule, 'automation_rule.updated');

            $rule->save();

            return $rule;
        });
    }

    /**
     * Flip the rule on or off. The runner reads `is_active` on its next pass;
     * nothing already sent is touched.
     */
    public function toggle(AutomationRule $rule): AutomationRule
    {
        return DB::transaction(function () use ($rule): AutomationRule {
            $rule->is_active = ! $rule->is_active;

            AuditLog::record($rule, $rule->is_active ? 'automation_rule.activated' : 'automation_rule.paused');

            $rule->save();

            return $rule;
        });
    }

    /**
     * Delete a rule, with its audit row. The runner stops using it at once;
     * the reminders it already sent stay in the log with their text
     * (`reminders.automation_rule_id` is set to null, REM-06, DATA-10).
     */
    public function delete(AutomationRule $rule): void
    {
        DB::transaction(function () use ($rule): void {
            AuditLog::record($rule, 'automation_rule.deleted', [
                'deleted' => $rule->only(['name', 'trigger', 'days', 'template_id', 'audience', 'is_active']),
            ]);

            $rule->delete();
        });
    }

    /**
     * "All hotels", "La Gazelle d'Or", "Reception + Spa", or both parts
     * joined with a middle dot, in the words the screen already uses.
     *
     * @param  list<int>  $hotelIds
     * @param  list<int>  $departmentIds
     */
    public function audienceLabel(array $hotelIds, array $departmentIds): string
    {
        $parts = [];

        if ($hotelIds !== []) {
            $parts[] = Hotel::withoutGlobalScopes()
                ->whereIn('id', $hotelIds)
                ->orderBy('name')
                ->pluck('name')
                ->implode(' + ');
        }

        if ($departmentIds !== []) {
            $parts[] = Department::query()
                ->whereIn('id', $departmentIds)
                ->orderBy('name')
                ->pluck('name')
                ->implode(' + ');
        }

        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        return $parts === [] ? __('All hotels') : implode(' · ', $parts);
    }

    /**
     * @param  array{name: string, trigger: AutomationTrigger, days: int|null, template_id: int, hotel_ids: list<int>, department_ids: list<int>, is_active: bool}  $data
     */
    private function apply(AutomationRule $rule, array $data): void
    {
        $hotelIds = $data['hotel_ids'];
        $departmentIds = $data['department_ids'];

        $rule->fill([
            'name' => $data['name'],
            'trigger' => $data['trigger'],
            'days' => $data['days'],
            'template_id' => $data['template_id'],
            'audience' => $hotelIds === [] && $departmentIds === []
                ? null
                : ['hotel_ids' => $hotelIds, 'department_ids' => $departmentIds],
            'audience_label' => $this->audienceLabel($hotelIds, $departmentIds),
            'is_active' => $data['is_active'],
        ]);
    }
}

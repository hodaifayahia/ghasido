<?php

namespace App\Services\Reminders;

use App\Enums\AccountStatus;
use App\Enums\AutomationTrigger;
use App\Enums\Role;
use App\Models\AutomationRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Date;

/**
 * Evaluates every active automation rule once (REM-03).
 *
 * Scheduled daily through App\Jobs\RunAutomationRules. For each rule it
 * builds the recipient query from the trigger, narrows it to the audience,
 * drops anybody the same rule reached inside the repeat window, and hands the
 * rest to ReminderService, which is where consent is checked (REM-05).
 *
 * Only active employees are ever candidates: managers and admins are not
 * learners, and a deactivated account gets nothing (AUTH-08).
 */
final class AutomationRunner
{
    public function __construct(private readonly ReminderService $reminders) {}

    /**
     * Run every active rule.
     *
     * @return array<int, array{rule: string, recipients: int, note: string|null}> keyed by rule id
     */
    public function run(): array
    {
        // Scheduled manual sends whose moment has passed go first, so a
        // worker outage never leaves a reminder scheduled for ever.
        $this->reminders->releaseDue();

        $summary = [];

        $rules = AutomationRule::query()
            ->active()
            ->with('template')
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            $summary[$rule->id] = $this->runRule($rule);
        }

        return $summary;
    }

    /**
     * Run one rule and stamp its last_run_at.
     *
     * @return array{rule: string, recipients: int, note: string|null}
     */
    public function runRule(AutomationRule $rule): array
    {
        $template = $rule->template;

        if ($template === null || ! $template->is_active) {
            return $this->finish($rule, 0, 'template_inactive');
        }

        $recipients = $this->recipientsFor($rule);

        if ($recipients->isEmpty()) {
            return $this->finish($rule, 0, null);
        }

        $sent = $this->reminders->send($recipients, $template, $rule->trigger->channel(), null, $rule);

        return $this->finish($rule, $sent->count(), null);
    }

    /**
     * The employees this rule would reach if it ran now.
     *
     * @return EloquentCollection<int, User>
     */
    public function recipientsFor(AutomationRule $rule): EloquentCollection
    {
        $query = User::query()
            ->where('status', AccountStatus::Active->value)
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', Role::Employee->value))
            ->with(['hotel', 'department'])
            ->orderBy('id');

        $this->applyTrigger($query, $rule);
        $this->applyAudience($query, $rule);
        $this->skipRecentlyReminded($query, $rule);

        $users = $query->get();

        if ($rule->trigger !== AutomationTrigger::PosttestAvailable) {
            return $users;
        }

        // Gate 2 of the journey (JOURNEY-04), judged by the learner lane's
        // User::postTestUnlocked().
        /** @var EloquentCollection<int, User> $unlocked */
        $unlocked = $users
            ->filter(fn (User $user): bool => $user->postTestUnlocked())
            ->values();

        return $unlocked;
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applyTrigger(Builder $query, AutomationRule $rule): void
    {
        switch ($rule->trigger) {
            case AutomationTrigger::InactiveDays:
                // The same clause the Messages screen's "Inactive N+ days"
                // filter uses, so the two can never disagree (REM-02, REM-03).
                RecipientQuery::idleSince($query, Date::now()->subDays($rule->inactivityDays()));
                break;

            case AutomationTrigger::NotStarted:
                RecipientQuery::notStarted($query);
                break;

            case AutomationTrigger::ConsentMissing:
                $query->whereNull('email_consent_at');
                break;

            case AutomationTrigger::PosttestAvailable:
                // Filtered in PHP after the fetch: the gate is a model method.
                break;
        }
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applyAudience(Builder $query, AutomationRule $rule): void
    {
        $hotelIds = $rule->hotelIds();
        $departmentIds = $rule->departmentIds();

        if ($hotelIds !== []) {
            $query->whereIn('hotel_id', $hotelIds);
        }

        if ($departmentIds !== []) {
            $query->whereIn('department_id', $departmentIds);
        }
    }

    /**
     * Leave out anybody this rule already reached inside its repeat window.
     *
     * Every row counts, blocked ones included, so a non-consenting employee
     * does not collect a fresh blocked row every morning.
     *
     * @param  Builder<User>  $query
     */
    private function skipRecentlyReminded(Builder $query, AutomationRule $rule): void
    {
        $since = Date::now()->subDays($rule->repeatWindowDays());

        $query->whereNotExists(function (QueryBuilder $recent) use ($rule, $since): void {
            $recent
                ->selectRaw('1')
                ->from('reminders')
                ->whereColumn('reminders.user_id', 'users.id')
                ->where('reminders.automation_rule_id', $rule->id)
                ->where('reminders.created_at', '>=', $since);
        });
    }

    /**
     * @return array{rule: string, recipients: int, note: string|null}
     */
    private function finish(AutomationRule $rule, int $recipients, ?string $note): array
    {
        $rule->forceFill(['last_run_at' => Date::now()])->save();

        return ['rule' => $rule->name, 'recipients' => $recipients, 'note' => $note];
    }
}

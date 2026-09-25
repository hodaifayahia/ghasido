<?php

namespace App\Services\Reminders;

use App\Enums\AccountStatus;
use App\Enums\AutomationTrigger;
use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
use App\Enums\Role;
use App\Models\AutomationRule;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Reminder;
use App\Models\ReminderTemplate;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;

/**
 * The read side of the Messages & Reminders screen: filters in, page props
 * out (REM-01..REM-07; spec 0003 Part D).
 *
 * Shapes only. The recipient rows come from RecipientQuery, the log from
 * Reminder, the figures from counts on the same scoped queries, so a stat
 * card and the table beneath it can never disagree. The controller only
 * calls build(); it writes no query.
 *
 * A manager gets their own hotel everywhere: recipients, hotel filter,
 * stats and log (REM-07).
 */
class MessagesDirectory
{
    public const DATE_FORMAT = 'd M Y';

    public const DATETIME_FORMAT = 'd M Y · H:i';

    /** Recipient rows per page. */
    public const PER_PAGE = 8;

    /** Delivery log rows per page. */
    public const LOG_PER_PAGE = 5;

    /** The stat card's window for "scheduled" reminders, in days. */
    public const SCHEDULED_WINDOW_DAYS = 7;

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request, User $actor): array
    {
        $filters = $this->filters($request, $actor);

        $recipients = RecipientQuery::apply(RecipientQuery::forActor($actor), $filters)
            ->paginate(self::PER_PAGE, ['users.*'], 'page')
            ->withQueryString();

        $logs = $this->logQuery($actor)
            ->paginate(self::LOG_PER_PAGE, ['*'], 'log_page')
            ->withQueryString();

        /** @var list<User> $recipientRows */
        $recipientRows = $recipients->items();
        /** @var list<Reminder> $logRows */
        $logRows = $logs->items();

        $templates = ReminderTemplate::query()->orderBy('name')->get();
        $rules = AutomationRule::query()->with('template')->orderBy('name')->get();

        return [
            'stats' => $this->stats($actor),
            'filters' => [
                ...$filters,
                'hotels' => $this->hotelOptions($actor),
                'departments' => $this->departmentOptions($actor),
                'consents' => $this->consentOptions(),
                'activities' => $this->activityOptions(),
            ],
            'recipients' => array_map(fn (User $user): array => $this->recipient($user), $recipientRows),
            'recipientsPagination' => $this->pagination($recipients),
            'templates' => $templates->map(fn (ReminderTemplate $template): array => $this->template($template))->all(),
            'automations' => $rules->map(fn (AutomationRule $rule): array => $this->rule($rule))->all(),
            'logs' => array_map(fn (Reminder $reminder): array => $this->log($reminder), $logRows),
            'logsPagination' => $this->pagination($logs),
            'options' => [
                'channels' => array_map(
                    static fn (ReminderChannel $channel): array => ['value' => $channel->value, 'label' => $channel->label()],
                    ReminderChannel::cases(),
                ),
                'triggers' => array_map(
                    static fn (AutomationTrigger $trigger): array => ['value' => $trigger->value, 'label' => $trigger->label()],
                    AutomationTrigger::cases(),
                ),
                'variables' => ReminderTemplate::VARIABLES,
                'inactiveDays' => RecipientQuery::inactiveDays(),
            ],
            // What the signed in user may do, decided by the policies so the
            // buttons and the server agree (ROLE-01). Presentation only.
            'abilities' => [
                'send' => Gate::forUser($actor)->allows('send', Reminder::class),
                'manageTemplates' => Gate::forUser($actor)->allows('create', ReminderTemplate::class),
                'manageRules' => Gate::forUser($actor)->allows('create', AutomationRule::class),
            ],
        ];
    }

    /**
     * The five filters as the request set them, with a manager pinned to
     * their own hotel whatever the query string says (REM-07).
     *
     * @return array{hotel: string, department: string, consent: string, activity: string, search: string}
     */
    public function filters(Request $request, User $actor): array
    {
        $hotel = (string) $request->query('hotel', RecipientQuery::ALL_HOTELS);

        if (! $actor->hasRole(Role::SuperAdmin->value)) {
            $hotel = (string) ($actor->hotel_id ?? RecipientQuery::ALL_HOTELS);
        }

        return [
            'hotel' => $hotel,
            'department' => (string) $request->query('department', RecipientQuery::ALL_DEPARTMENTS),
            'consent' => (string) $request->query('consent', RecipientQuery::CONSENT_GRANTED),
            'activity' => (string) $request->query('activity', RecipientQuery::ACTIVITY_INACTIVE),
            'search' => trim((string) $request->query('search', '')),
        ];
    }

    // ------------------------------------------------------------------ stats

    /**
     * @return list<array{key: string, value: int, label: string, detail: string}>
     */
    private function stats(User $actor): array
    {
        $employees = RecipientQuery::forActor($actor);
        $total = (clone $employees)->count();
        $consented = (clone $employees)->whereNotNull('users.email_consent_at')->count();

        $inactiveDays = RecipientQuery::inactiveDays();
        $followUp = (clone $employees)->where('users.status', AccountStatus::Active->value);
        RecipientQuery::idleSince($followUp, RecipientQuery::inactivityCutoff());

        $now = Date::now();

        $scheduled = $this->logQuery($actor)
            ->where('status', ReminderStatus::Scheduled->value)
            ->whereBetween('scheduled_for', [$now, $now->copy()->addDays(self::SCHEDULED_WINDOW_DAYS)])
            ->count();

        $sentToday = $this->logQuery($actor)
            ->where('status', ReminderStatus::Sent->value)
            ->whereBetween('sent_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
            ->count();

        $templates = ReminderTemplate::query()->active()->count();

        return [
            [
                'key' => 'consentedEmployees',
                'value' => $consented,
                'label' => __('Consent Granted'),
                'detail' => __('of :total employees', ['total' => $total]),
            ],
            [
                'key' => 'scheduledReminders',
                'value' => $scheduled,
                'label' => __('Scheduled Reminders'),
                'detail' => __('next :days days', ['days' => self::SCHEDULED_WINDOW_DAYS]),
            ],
            [
                'key' => 'followUpNeeded',
                'value' => $followUp->count(),
                'label' => __('Need Follow-up'),
                'detail' => __('inactive :days+ days', ['days' => $inactiveDays]),
            ],
            [
                'key' => 'sentToday',
                'value' => $sentToday,
                'label' => __('Sent Today'),
                'detail' => __('manual + automatic'),
            ],
            [
                'key' => 'templates',
                'value' => $templates,
                'label' => __('Templates Ready'),
                'detail' => __('email reminder flows'),
            ],
        ];
    }

    // ------------------------------------------------------------------- rows

    /**
     * One recipient row, in the shape resources/js/types/messages.ts calls
     * MessageRecipient.
     *
     * @return array<string, mixed>
     */
    private function recipient(User $user): array
    {
        $lastActivity = $user->last_activity_at;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'hotel' => $user->hotel->name ?? '—',
            'hotelId' => $user->hotel_id,
            'department' => $user->department->name ?? '—',
            'departmentId' => $user->department_id,
            'lastActivity' => $lastActivity === null ? __('Never') : $lastActivity->format(self::DATE_FORMAT),
            'inactivityLabel' => $lastActivity === null ? __('Not started') : $lastActivity->diffForHumans(),
            'consentStatus' => $user->email_consent_at === null ? 'not_granted' : 'granted',
            'status' => $this->recipientStatus($user),
        ];
    }

    /**
     * The status pill: a deactivated account is `inactive`; without consent
     * the employee is `consent_pending`; active inside the inactivity window
     * is `active`; otherwise the training is `in_progress` but idle.
     */
    private function recipientStatus(User $user): string
    {
        if ($user->status !== AccountStatus::Active) {
            return 'inactive';
        }

        if ($user->email_consent_at === null) {
            return 'consent_pending';
        }

        $lastActivity = $user->last_activity_at;

        if ($lastActivity !== null && $lastActivity->greaterThanOrEqualTo(RecipientQuery::inactivityCutoff())) {
            return 'active';
        }

        return 'in_progress';
    }

    /**
     * @return array<string, mixed>
     */
    private function template(ReminderTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'subject' => $template->subject,
            'body' => $template->body,
            'audience' => $template->audience_label ?? '',
            'trigger' => $template->trigger_label ?? '',
            'isActive' => $template->is_active,
            'updatedAt' => self::formatDate($template->updated_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rule(AutomationRule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'trigger' => $rule->trigger->label($rule->days),
            'triggerKey' => $rule->trigger->value,
            'days' => $rule->days,
            'templateId' => $rule->template_id,
            'templateName' => $rule->template->name ?? '',
            'audience' => $rule->audience_label ?? __('All hotels'),
            'hotelIds' => $rule->hotelIds(),
            'departmentIds' => $rule->departmentIds(),
            'active' => $rule->is_active,
            'lastRunAt' => $rule->last_run_at === null ? null : $rule->last_run_at->format(self::DATETIME_FORMAT),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function log(Reminder $reminder): array
    {
        $moment = $reminder->status === ReminderStatus::Scheduled
            ? $reminder->scheduled_for
            : ($reminder->sent_at ?? $reminder->created_at);

        return [
            'id' => $reminder->id,
            'recipient' => $reminder->user->name ?? __('Unknown employee'),
            'template' => $reminder->template->name ?? $reminder->subject,
            'channel' => $reminder->channel->value,
            'sentAt' => $moment === null ? '—' : $moment->format(self::DATETIME_FORMAT),
            'status' => $reminder->status->value,
            'reason' => $reminder->blocked_reason,
            'automatic' => $reminder->automation_rule_id !== null,
        ];
    }

    // ---------------------------------------------------------------- queries

    /**
     * The reminder log this actor may read: every row for the Super Admin,
     * the rows of their own employees for a manager (REM-06, REM-07).
     *
     * @return Builder<Reminder>
     */
    private function logQuery(User $actor): Builder
    {
        $query = Reminder::query()
            ->with(['user', 'template'])
            ->orderByRaw('COALESCE(sent_at, scheduled_for, created_at) DESC')
            ->orderByDesc('id');

        if (! $actor->hasRole(Role::SuperAdmin->value)) {
            $hotelId = $actor->hotel_id ?? 0;

            $query->whereHas('user', fn (Builder $user) => $user->where('users.hotel_id', $hotelId));
        }

        return $query;
    }

    // ---------------------------------------------------------------- options

    /**
     * @return list<array{value: string, label: string}>
     */
    private function hotelOptions(User $actor): array
    {
        // Hotel carries ScopedToHotel, so a manager lists only their own.
        $hotels = Hotel::query()->notArchived()->orderBy('name')->get(['id', 'name']);

        $options = $actor->hasRole(Role::SuperAdmin->value)
            ? [['value' => RecipientQuery::ALL_HOTELS, 'label' => __('All Hotels')]]
            : [];

        foreach ($hotels as $hotel) {
            $options[] = ['value' => (string) $hotel->id, 'label' => $hotel->name];
        }

        return $options;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function departmentOptions(User $actor): array
    {
        $query = Department::query()->where('is_active', true)->orderBy('position')->orderBy('name');

        if (! $actor->hasRole(Role::SuperAdmin->value)) {
            $hotelId = $actor->hotel_id ?? 0;

            $query->where(fn (Builder $scope) => $scope->whereNull('hotel_id')->orWhere('hotel_id', $hotelId));
        }

        $options = [['value' => RecipientQuery::ALL_DEPARTMENTS, 'label' => __('All Departments')]];

        foreach ($query->get(['id', 'name']) as $department) {
            $options[] = ['value' => (string) $department->id, 'label' => $department->name];
        }

        return $options;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function consentOptions(): array
    {
        return [
            ['value' => RecipientQuery::CONSENT_GRANTED, 'label' => __('Consent Granted')],
            ['value' => RecipientQuery::CONSENT_MISSING, 'label' => __('Consent Missing')],
            ['value' => RecipientQuery::ALL_CONSENT, 'label' => __('All Consent')],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function activityOptions(): array
    {
        return [
            ['value' => RecipientQuery::ACTIVITY_INACTIVE, 'label' => __('Inactive :days+ days', ['days' => RecipientQuery::inactiveDays()])],
            ['value' => RecipientQuery::ACTIVITY_NOT_STARTED, 'label' => __('Not Started')],
            ['value' => RecipientQuery::ACTIVITY_PRETEST_ONLY, 'label' => __('Pre-test Only')],
            ['value' => RecipientQuery::ALL_ACTIVITY, 'label' => __('All Activity')],
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $page
     * @return array{from: int, to: int, total: int, currentPage: int, lastPage: int, pages: list<int|string>}
     */
    private function pagination(LengthAwarePaginator $page): array
    {
        return [
            'from' => (int) ($page->firstItem() ?? 0),
            'to' => (int) ($page->lastItem() ?? 0),
            'total' => $page->total(),
            'currentPage' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
            'pages' => $this->pageWindow($page->currentPage(), $page->lastPage()),
        ];
    }

    /**
     * 1 … current-1 current current+1 … last, with the ends always present.
     *
     * @return list<int|string>
     */
    private function pageWindow(int $current, int $last): array
    {
        if ($last <= 7) {
            return range(1, max(1, $last));
        }

        $pages = [1];

        if ($current > 3) {
            $pages[] = 'ellipsis';
        }

        foreach (range(max(2, $current - 1), min($last - 1, $current + 1)) as $page) {
            $pages[] = $page;
        }

        if ($current < $last - 2) {
            $pages[] = 'ellipsis';
        }

        $pages[] = $last;

        return $pages;
    }

    public static function formatDate(?CarbonInterface $date): string
    {
        return $date === null ? '—' : $date->format(self::DATE_FORMAT);
    }
}

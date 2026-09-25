<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\Reminder;
use App\Models\User;
use App\Services\Learning\JourneyService;
use App\Services\Learning\TrainingDepartments;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(
        private readonly JourneyService $journey,
        private readonly TrainingDepartments $training,
    ) {}

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // The signed in role and the capabilities behind it, resolved once per
        // request with the role relation eager loaded (spec 0001, AC-5).
        // share() runs for guests too, so both reads are null safe and both
        // keys are always present: `null` and `[]` when nobody is signed in
        // (invariant 8). The sidebar reads `permissions` to hide what the user
        // cannot open, which is presentation only; the server decides access.
        /** @var User|null $user */
        $user = $request->user();
        $user?->loadMissing('roles.permissions');
        $user?->append('role');
        $notifications = $user?->reminders()
            ->visibleInNotificationCenter()
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->get() ?? collect();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                // The hotel and department names ride along as two strings
                // rather than two loaded relations, so a hotel's settings and
                // manager contact never ship on every response (spec 0003
                // Part D). Both are null for the Super Admin.
                'user' => $user === null ? null : [
                    ...$user->toArray(),
                    'department_name' => $user->department_id === null ? null : $user->department()->value('name'),
                    'hotel_name' => $user->hotel_id === null ? null : $user->hotel()->withoutGlobalScopes()->value('name'),
                ],
                'permissions' => $user?->permissionNames() ?? [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // The global topbar notification menu (REM-08): only this user's
            // delivered reminders from the last 24 hours. Older rows remain
            // in the reminder log (DATA-10).
            'notifications' => [
                'unread' => $user === null
                    ? 0
                    : $user->reminders()->unreadNotifications()->count(),
                'items' => $notifications
                    ->map(fn (Reminder $reminder): array => [
                        'id' => $reminder->id,
                        'channel' => $reminder->channel->value,
                        'subject' => $reminder->subject,
                        'body' => $reminder->body,
                        'sentAt' => $reminder->sent_at?->toIso8601String() ?? '',
                        'expiresAt' => $reminder->sent_at?->copy()
                            ->addHours(Reminder::IN_APP_EXPIRY_HOURS)
                            ->toIso8601String() ?? '',
                        'read' => $reminder->read_at !== null,
                        'readUrl' => route('notifications.read', ['reminder' => $reminder]),
                    ])
                    ->values()
                    ->all(),
            ],
            // The employee journey's gates and figures (JOURNEY-01..05, PROG-05;
            // spec 0003 Part E). Null for every non-learner: the learner shell
            // reads it. A manager training as an employee gets it too, once
            // they have chosen a department (client decision 2026-09-23).
            'journey' => $this->journeyFor($user),
            // The manager's training department switcher (client decision
            // 2026-09-23): null unless a manager is on a learner route.
            'trainingContext' => $this->trainingContextFor($request, $user),
        ];
    }

    /**
     * The learner journey summary for anyone who works through the training:
     * an employee always, a manager once they have chosen a department to
     * train in. Null for every other user.
     *
     * @return array<string, mixed>|null
     */
    private function journeyFor(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        if ($user->hasRole(Role::Employee->value)) {
            return $this->journey->summary($user);
        }

        if ($user->hasRole(Role::Manager->value) && $user->learningDepartmentId() !== null) {
            return $this->journey->summary($user);
        }

        return null;
    }

    /**
     * The manager's training department switcher: the departments they may
     * train in and the one they are in now. Only on the learner routes, and
     * only for a manager; null for everyone else.
     *
     * @return array{departments: list<array{id: int, name: string}>, currentDepartmentId: int|null}|null
     */
    private function trainingContextFor(Request $request, ?User $user): ?array
    {
        if ($user === null || ! $user->hasRole(Role::Manager->value) || ! $request->routeIs('learn.*')) {
            return null;
        }

        return [
            'departments' => $this->training->options($user),
            'currentDepartmentId' => $user->learningDepartmentId(),
        ];
    }
}

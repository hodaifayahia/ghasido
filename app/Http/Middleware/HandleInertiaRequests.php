<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\HotelAiPointTopUpRequest;
use App\Models\Reminder;
use App\Models\User;
use App\Services\Learning\JourneyService;
use App\Services\Learning\TrainingDepartments;
use App\Services\Subscriptions\AiPointsBalanceService;
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
        private readonly AiPointsBalanceService $aiPoints,
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
        // The web guard by name: the owner console's `owner` guard (spec
        // 0007) is never an app user, even when it is the request's guard.
        $user = $request->user('web');
        $user?->loadMissing('roles.permissions');
        $user?->append('role');
        $notifications = $user?->reminders()
            ->visibleInNotificationCenter()
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->get() ?? collect();
        $topUpRequests = $user?->hasRole(Role::SuperAdmin->value)
            ? HotelAiPointTopUpRequest::query()
                ->where('status', 'pending')
                ->with(['hotel', 'requester'])
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
            : collect();
        $unreadTopUpRequests = $user?->hasRole(Role::SuperAdmin->value)
            ? HotelAiPointTopUpRequest::query()
                ->where('status', 'pending')
                ->whereNull('read_at')
                ->count()
            : 0;
        $notificationItems = $notifications
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
            ->concat($topUpRequests->map(fn (HotelAiPointTopUpRequest $topUpRequest): array => [
                // Negative IDs keep this global bell key unique from reminder IDs.
                'id' => -$topUpRequest->id,
                'channel' => 'in_app',
                'subject' => __('AI points depleted at :hotel', [
                    'hotel' => $topUpRequest->hotel?->name ?? __('Archived hotel'),
                ]),
                'body' => __(':requester requested a paid point recharge. Review the request and record payment in Subscriptions.', [
                    'requester' => $topUpRequest->requester?->name ?? __('A hotel administrator'),
                ]),
                'sentAt' => $topUpRequest->created_at?->toIso8601String() ?? '',
                'expiresAt' => null,
                'read' => $topUpRequest->read_at !== null,
                'readUrl' => route('subscriptions.ai-point-top-up-requests.read', ['topUpRequest' => $topUpRequest]),
            ]))
            ->sortByDesc('sentAt')
            ->values();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                // The hotel and department names ride along as two strings
                // rather than two loaded relations, so a hotel's settings and
                // manager contact never ship on every response (spec 0003
                // Part D). Both are null for the Super Admin.
                //
                // Only the fields a screen reads go out: every response used to
                // carry the whole row, the loaded roles with every permission,
                // and research columns such as the participant code (PRIV-03;
                // spec 0005 §1.9). Capabilities travel as `permissions` below.
                'user' => $user === null ? null : [
                    ...$user->only([
                        'id', 'name', 'username', 'email', 'email_verified_at',
                        'role', 'status', 'hotel_id', 'department_id',
                        'created_at', 'updated_at',
                    ]),
                    'department_name' => $user->department_id === null ? null : $user->department()->value('name'),
                    'hotel_name' => $user->hotel_id === null ? null : $user->hotel()->withoutGlobalScopes()->value('name'),
                ],
                'permissions' => $user?->permissionNames() ?? [],
            ],
            // Monthly employee or hotel AI balance for the shared navbar
            // (AIL-01). Hotel admins/managers receive only their own hotel's aggregate (ROLE-02).
            'aiPointBalance' => $user === null ? null : $this->aiPoints->forUser($user),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // The global topbar notification menu (REM-08, AIL-01): this user's
            // recent reminders plus outstanding Super Admin recharge requests.
            'notifications' => [
                'unread' => $notifications->whereNull('read_at')->count()
                    + $unreadTopUpRequests,
                'items' => $notificationItems->all(),
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

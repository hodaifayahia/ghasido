<?php

namespace App\Http\Middleware;

use App\Enums\EnglishLevel;
use App\Enums\HotelAccessState;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\ContactMessage;
use App\Models\Hotel;
use App\Models\HotelAiPointTopUpRequest;
use App\Models\PaymentSubmission;
use App\Models\Reminder;
use App\Models\User;
use App\Services\I18n\InterfaceLanguages;
use App\Services\Learning\JourneyService;
use App\Services\Learning\TrainingDepartments;
use App\Services\Meaning\HelperLanguages;
use App\Services\Subscriptions\AiPointsBalanceService;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            ->orderByDesc('id')
            ->get() ?? collect();
        // Payments sent from the checkout wait for whoever manages
        // subscriptions (client request 2026-09-27).
        $managesPayments = $user?->can(Permission::SubscriptionsManage->value) ?? false;
        $pendingPayments = $managesPayments ? PaymentSubmission::query()->pending()->count() : 0;
        $paymentItems = $managesPayments
            ? PaymentSubmission::query()
                ->pending()
                ->with('hotel')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
            : collect();
        $unreadPayments = $managesPayments
            ? PaymentSubmission::query()->pending()->whereNull('read_at')->count()
            : 0;
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
        // New hotel requests sent without a payment (the free sign-up form)
        // and new contact messages ring the bell too (client report
        // 2026-10-01: "a request came and the bell said nothing"). A hotel
        // request with a payment already shows as the payment.
        $hotelRequests = $user?->can(Permission::HotelsApprove->value)
            ? Hotel::query()
                ->withoutGlobalScopes()
                ->where('access_state', HotelAccessState::Pending->value)
                ->whereDoesntHave('paymentSubmissions')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
            : collect();
        $contactMessages = $user?->can(Permission::LandingManage->value)
            ? ContactMessage::query()->whereNull('read_at')->orderByDesc('created_at')->limit(10)->get()
            : collect();

        // A reminder with no link of its own opens the learner's messages;
        // other roles have no reminder page, so the click only marks it read.
        $reminderPage = $user?->hasRole(Role::Employee->value)
            ? route('learn.messages', [], false)
            : null;

        $notificationItems = $notifications
            ->map(fn (Reminder $reminder): array => [
                'id' => $reminder->id,
                'channel' => $reminder->channel->value,
                'subject' => $reminder->subject,
                'body' => $reminder->body,
                'sentAt' => $reminder->noticedAt()?->toIso8601String() ?? '',
                'expiresAt' => $reminder->noticedAt()?->copy()
                    ->addHours(Reminder::IN_APP_EXPIRY_HOURS)
                    ->toIso8601String() ?? '',
                'read' => $reminder->read_at !== null,
                'readUrl' => route('notifications.read', ['reminder' => $reminder]),
                // Where a click goes (client request 2026-10-03).
                'url' => $reminder->link ?? $reminderPage,
            ])
            ->concat($topUpRequests->map(fn (HotelAiPointTopUpRequest $topUpRequest): array => [
                // Negative IDs keep this global bell key unique from reminder IDs.
                'id' => -$topUpRequest->id,
                'channel' => 'in_app',
                'subject' => __('AI points depleted at :hotel', [
                    'hotel' => $topUpRequest->hotel->name ?? __('Archived hotel'),
                ]),
                'body' => __(':requester requested a paid point recharge. Review the request and record payment in Subscriptions.', [
                    'requester' => $topUpRequest->requester->name ?? __('A hotel administrator'),
                ]),
                'sentAt' => $topUpRequest->created_at?->toIso8601String() ?? '',
                'expiresAt' => null,
                'read' => $topUpRequest->read_at !== null,
                'readUrl' => route('subscriptions.ai-point-top-up-requests.read', ['topUpRequest' => $topUpRequest]),
                'url' => route('subscriptions', [], false),
            ]))
            ->concat($paymentItems->map(fn (PaymentSubmission $payment): array => [
                // Kept apart from reminder and recharge ids.
                'id' => -1_000_000 - $payment->id,
                'channel' => 'in_app',
                'subject' => __('New payment from :customer', [
                    'customer' => $payment->hotel->name ?? $payment->payer_name,
                ]),
                'body' => __(':plan plan · :method. Check the receipt and approve the account.', [
                    'plan' => $payment->plan_name,
                    'method' => $payment->methodLabel(),
                ]),
                'sentAt' => $payment->created_at?->toIso8601String() ?? '',
                'expiresAt' => null,
                'read' => $payment->read_at !== null,
                'readUrl' => route('payments.read', ['payment' => $payment]),
                'url' => route('payments', ['payment' => $payment->id], false),
            ]))
            ->concat($hotelRequests->map(fn (Hotel $hotel): array => [
                'id' => -2_000_000 - $hotel->id,
                'channel' => 'in_app',
                'subject' => __('New hotel request: :hotel', ['hotel' => $hotel->name]),
                'body' => __(':manager from :city asked to join. Review it in Hotels.', [
                    'manager' => $hotel->manager_name,
                    'city' => $hotel->city,
                ]),
                'sentAt' => $hotel->created_at?->toIso8601String() ?? '',
                'expiresAt' => null,
                'read' => $hotel->request_read_at !== null,
                'readUrl' => route('hotels.request-seen', ['hotel' => $hotel]),
                'url' => route('hotels.show', ['hotel' => $hotel], false),
            ]))
            ->concat($contactMessages->map(fn (ContactMessage $message): array => [
                'id' => -3_000_000 - $message->id,
                'channel' => 'in_app',
                'subject' => $message->user_id !== null
                    ? __('Support request from :name', ['name' => $message->name.($message->organisation ? ' · '.$message->organisation : '')])
                    : __('New message from :name', ['name' => $message->name]),
                'body' => Str::limit($message->message, 140),
                'sentAt' => $message->created_at?->toIso8601String() ?? '',
                'expiresAt' => null,
                'read' => false,
                'readUrl' => route('contact-messages.seen', ['contactMessage' => $message]),
                'url' => route('inbox', ['message' => $message->id], false),
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
                    // The one-time welcome animation after the very first
                    // sign-in (client request 2026-09-26).
                    'show_welcome' => $user->welcomed_at === null,
                ],
                'permissions' => $user?->permissionNames() ?? [],
            ],
            // Monthly employee or hotel AI balance for the shared navbar
            // (AIL-01). Hotel admins/managers receive only their own hotel's aggregate (ROLE-02).
            'aiPointBalance' => $user === null ? null : $this->aiPoints->forUser($user),
            // The interface language and its direction (I18N-02). The
            // Arabic strings themselves ship as a lazily loaded bundle, not
            // on every response.
            'locale' => [
                'current' => app()->getLocale(),
                'direction' => Locales::direction(app()->getLocale()),
                // The menu's languages: English, Arabic and those added in
                // Settings (client request 2026-10-03).
                'available' => InterfaceLanguages::options(),
            ],
            // The Payments nav badge (client request 2026-09-27).
            'pendingPayments' => $pendingPayments,
            // The Inbox nav badge (client request 2026-10-03).
            // A closure, so opening a message counts it down on that page.
            'unreadInbox' => fn (): int => $user?->can(Permission::LandingManage->value)
                ? ContactMessage::query()->whereNull('read_at')->count()
                : 0,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // The global topbar notification menu (REM-08, AIL-01): this user's
            // recent reminders plus outstanding Super Admin recharge requests.
            'notifications' => [
                'unread' => $notifications->whereNull('read_at')->count()
                    + $unreadTopUpRequests
                    + $unreadPayments
                    + $hotelRequests->whereNull('request_read_at')->count()
                    + $contactMessages->count(),
                'items' => $notificationItems->all(),
            ],
            // The employee journey's gates and figures (JOURNEY-01..05, PROG-05;
            // spec 0003 Part E). Null for every non-learner: the learner shell
            // reads it. A manager training as an employee gets it too, once
            // they have chosen a department (client decision 2026-09-23).
            'journey' => $this->journeyFor($user),
            // The learner's level and a pending move-up suggestion (client
            // decision 2026-09-30). Null for anyone who is not a learner.
            'learnerLevel' => $this->learnerLevelFor($user),
            // The Show Meaning language: the learner's choice or the
            // platform default (client request 2026-09-30). Everyone reads
            // it, so the meaning panels know the language and direction.
            'helperLanguage' => $this->helperLanguageFor($user),
            // The manager's training department switcher (client decision
            // 2026-09-23): null unless a manager is on a learner route.
            'trainingContext' => $this->trainingContextFor($request, $user),
        ];
    }

    /**
     * @return array{code: string, name: string, dir: string, flag: string|null, chosen: bool, options: list<array{value: string, label: string, dir: string, name: string, native: string, flag: string|null}>, all: list<array{value: string, label: string, dir: string, name: string, native: string, flag: string|null, active: bool}>, updateUrl: string}|null
     */
    private function helperLanguageFor(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $languages = app(HelperLanguages::class);
        $code = $languages->forUser($user);

        return [
            'code' => $code,
            'name' => $languages->name($code),
            'dir' => $languages->direction($code),
            'flag' => HelperLanguages::flag($code),
            // False until the user picks one: the app then asks once, with
            // flags, on sign-in (client request 2026-10-01).
            'chosen' => $languages->isActive($user->meaning_locale),
            'options' => $languages->options(),
            // Every language, on or off, for the builders' translation
            // field (client request 2026-10-01). A handful of rows.
            'all' => $languages->editorOptions(),
            'updateUrl' => route('helper-language.update'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function learnerLevelFor(?User $user): ?array
    {
        if ($user === null || $this->journeyFor($user) === null) {
            return null;
        }

        return [
            'current' => $user->english_level?->value,
            'currentLabel' => $user->english_level?->label(),
            'suggestion' => $user->level_suggestion?->value,
            'suggestionLabel' => $user->level_suggestion?->label(),
            'options' => EnglishLevel::options(),
            'updateUrl' => route('learn.level.update'),
            'answerUrl' => route('learn.level.answer'),
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

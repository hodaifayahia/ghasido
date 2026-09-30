<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\ApprovalState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Subscriptions\SaveIndividualRequest;
use App\Models\Department;
use App\Models\IndividualSubscription;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Services\Ai\UsageMeter;
use App\Services\Subscriptions\IndividualSubscriptionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Individual subscribers (user request 2026-09-25): learners who use the app
 * and its AI with no hotel, each on their own configuration. Behind
 * `subscriptions.manage` (routes/admin/subscriptions.php); the request class
 * checks it again.
 */
class IndividualsController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request, UsageMeter $meter): Response
    {
        $search = trim((string) $request->query('search', ''));
        $state = (string) $request->query('state', 'all');

        $query = $this->individuals()
            ->with(['individualSubscription.departments', 'individualSubscription.plan', 'individualSubscription.paymentSubmissions', 'department'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('username', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            // Bought online and waiting for the payment to be confirmed
            // (client request 2026-09-27).
            ->when($state === 'pending', fn (Builder $query) => $query->whereHas(
                'individualSubscription',
                fn (Builder $sub) => $sub->where('approval_state', ApprovalState::Pending->value),
            ))
            ->when($state === 'inactive', fn (Builder $query) => $query->where('status', AccountStatus::Inactive->value))
            ->when($state === 'ended', fn (Builder $query) => $query->whereHas(
                'individualSubscription',
                fn (Builder $sub) => $sub->whereDate('ends_on', '<', Date::today()),
            ))
            // Waiting for approval first (client request 2026-09-29).
            ->orderByDesc(IndividualSubscription::query()
                ->selectRaw('case when approval_state = ? then 1 else 0 end', [ApprovalState::Pending->value])
                ->whereColumn('individual_subscriptions.user_id', 'users.id')
                ->limit(1))
            ->orderByDesc('id');

        $page = $query->paginate(self::PER_PAGE)->withQueryString();

        $rows = collect($page->items())->map(fn (User $user): array => $this->row($user, $meter))->values()->all();

        $today = Date::today();
        $all = IndividualSubscription::query()->whereHas('user', fn (Builder $query) => $query->whereNull('hotel_id'));

        return Inertia::render('admin/Individuals', [
            'individuals' => $rows,
            'pagination' => [
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'total' => $page->total(),
                'from' => $page->firstItem() ?? 0,
                'to' => $page->lastItem() ?? 0,
            ],
            'filters' => ['search' => $search, 'state' => $state],
            'stats' => [
                'total' => (clone $all)->count(),
                'pending' => (clone $all)->where('approval_state', ApprovalState::Pending->value)->count(),
                'active' => $this->individuals()->where('status', AccountStatus::Active->value)
                    ->whereHas('individualSubscription', fn (Builder $sub) => $sub
                        ->where(fn (Builder $q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $today))
                        ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today)))
                    ->count(),
                'endingSoon' => (clone $all)->whereDate('ends_on', '>=', $today)->whereDate('ends_on', '<=', $today->copy()->addDays(14))->count(),
                'withAi' => (clone $all)->where('ai_enabled', true)->count(),
            ],
            'departments' => Department::query()
                ->whereNull('hotel_id')
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Department $department): array => ['value' => (string) $department->id, 'label' => $department->name])
                ->values()
                ->all(),
            'defaults' => [
                'aiPoints' => 3000,
                'aiActionPoints' => 50,
                'voicePointsPer10Minutes' => 100,
                'dailyAiTurns' => UsageMeter::perEmployeeDailyTurns(),
            ],
        ]);
    }

    public function store(SaveIndividualRequest $request, IndividualSubscriptionService $service): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user('web');
        $user = $service->create($request->individualData(), $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name can now sign in as an individual subscriber.', ['name' => $user->name])]);

        return back();
    }

    public function update(SaveIndividualRequest $request, User $individual, IndividualSubscriptionService $service): RedirectResponse
    {
        $this->assertIndividual($individual);
        $service->update($individual, $request->individualData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was saved.', ['name' => $individual->name])]);

        return back();
    }

    /**
     * Confirm the payment of an individual who bought online: the account
     * opens and they are emailed right away (client request 2026-09-27).
     */
    public function approve(Request $request, User $individual, IndividualSubscriptionService $service): RedirectResponse
    {
        $this->assertIndividual($individual);
        /** @var User $actor */
        $actor = $request->user('web');
        $service->approve($this->subscription($individual), $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is approved and has been emailed.', ['name' => $individual->name])]);

        return back();
    }

    public function reject(Request $request, User $individual, IndividualSubscriptionService $service): RedirectResponse
    {
        $this->assertIndividual($individual);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        /** @var User $actor */
        $actor = $request->user('web');
        $service->reject($this->subscription($individual), trim((string) $data['reason']), $actor);

        Inertia::flash('toast', ['type' => 'info', 'message' => __(':name was rejected and told why by email.', ['name' => $individual->name])]);

        return back();
    }

    public function toggle(User $individual, IndividualSubscriptionService $service): RedirectResponse
    {
        $this->assertIndividual($individual);

        // A subscription bought online opens by approval, never by the
        // on/off switch, so a payment is never skipped by mistake.
        abort_if($this->subscription($individual)->approval_state !== ApprovalState::Approved, 409, __('Approve or reject this subscription first.'));

        $user = $service->toggle($individual);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $user->status === AccountStatus::Active
                ? __(':name was activated.', ['name' => $user->name])
                : __(':name was deactivated. Their progress is kept.', ['name' => $user->name]),
        ]);

        return back();
    }

    /**
     * @return Builder<User>
     */
    private function individuals(): Builder
    {
        return User::query()->whereNull('hotel_id')->whereHas('individualSubscription');
    }

    private function assertIndividual(User $user): void
    {
        abort_unless($user->hotel_id === null && $user->individualSubscription !== null, 404);
    }

    private function subscription(User $user): IndividualSubscription
    {
        $subscription = $user->individualSubscription;
        abort_unless($subscription instanceof IndividualSubscription, 404);

        return $subscription;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $user, UsageMeter $meter): array
    {
        /** @var IndividualSubscription $subscription */
        $subscription = $user->individualSubscription;
        $used = $meter->pointsUsedThisMonth($user);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            // Main department first (users.department_id), then the others.
            'departmentIds' => $subscription->departments->map(fn (Department $department): string => (string) $department->id)->values()->all(),
            'department' => $subscription->departments->isEmpty()
                ? ($user->department->name ?? '—')
                : $subscription->departments->pluck('name')->implode(', '),
            // The learner's chosen level (client request 2026-09-30).
            'level' => $user->english_level?->label(),
            'status' => $user->status->value,
            'windowState' => $subscription->windowState(),
            'startsOn' => $subscription->starts_on?->toDateString(),
            'endsOn' => $subscription->ends_on?->toDateString(),
            'daysRemaining' => $subscription->daysRemaining(),
            'aiEnabled' => $subscription->ai_enabled,
            'voiceEnabled' => $subscription->voice_enabled,
            'aiPoints' => $user->ai_points_allocated,
            'aiPointsUsed' => $used,
            'aiPointsLeft' => max(0, $user->ai_points_allocated - $used),
            'dailyAiTurns' => $subscription->daily_ai_turns,
            'aiActionPoints' => $subscription->ai_action_points,
            'voicePointsPer10Minutes' => $subscription->voice_points_per_10_minutes,
            'priceDzd' => $subscription->price_dzd,
            'priceUsd' => $subscription->price_usd,
            'paymentReference' => $subscription->payment_reference,
            'notes' => $subscription->notes,
            'lastActivity' => $user->last_activity_at?->diffForHumans(),
            'phone' => $user->phone,
            // Bought online (client request 2026-09-27): the plan, whether it
            // still waits for approval, and the payment sent with it.
            'planName' => $subscription->plan?->name,
            'approvalState' => $subscription->approval_state->value,
            'approvalLabel' => $subscription->approval_state->label(),
            'rejectionReason' => $subscription->rejection_reason,
            'payment' => $this->latestPayment($subscription),
        ];
    }

    /**
     * @return array{id: int, method: string, amount: float, currency: string, reference: string|null, receiptUrl: string|null, isImage: bool, status: string, submittedAt: string|null}|null
     */
    private function latestPayment(IndividualSubscription $subscription): ?array
    {
        $payment = $subscription->paymentSubmissions->sortByDesc('id')->first();

        if (! $payment instanceof PaymentSubmission) {
            return null;
        }

        return [
            'id' => $payment->id,
            'method' => $payment->methodLabel(),
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'reference' => $payment->reference,
            'receiptUrl' => $payment->proof_path !== null ? route('payments.receipt', $payment) : null,
            'isImage' => $payment->isImageProof(),
            'status' => $payment->status->value,
            'submittedAt' => $payment->created_at?->toIso8601String(),
        ];
    }
}

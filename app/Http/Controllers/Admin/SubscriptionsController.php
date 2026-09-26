<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Subscriptions\UpdateSubscriptionPlanRequest;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\SubscriptionPaymentMethod;
use App\Models\SubscriptionPlan;
use App\Services\Subscriptions\HotelAiPointTopUpService;
use App\Services\Subscriptions\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Subscription catalog and hotel assignments (SUB-01..05, AIL-01). */
final class SubscriptionsController extends Controller
{
    public function index(): Response
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $plans = SubscriptionPlan::query()->orderBy('employee_limit')->orderBy('id')->get();
        $hotels = Hotel::query()
            ->notArchived()
            ->with('subscriptionPlan')
            ->withSeatCounts()
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/Subscriptions', [
            'plans' => $plans->map(fn (SubscriptionPlan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'employeeLimit' => $plan->employee_limit,
                'priceDzd' => $plan->price_dzd,
                'priceUsd' => $plan->price_usd,
                'extraPointsPriceDzd' => $plan->extra_points_price_dzd,
                'extraPointsPriceUsd' => $plan->extra_points_price_usd,
                'extraSeatPriceDzd' => $plan->extra_seat_price_dzd,
                'extraSeatPriceUsd' => $plan->extra_seat_price_usd,
                'pointsPerEmployee' => $plan->points_per_employee,
                'bonusPointsPerEmployee' => $plan->bonus_points_per_employee,
                'voicePointsPer10Minutes' => $plan->voice_points_per_10_minutes,
                'aiActionPoints' => $plan->ai_action_points,
                'isActive' => $plan->is_active,
                'hotelCount' => $plan->hotels()->count(),
                'pointPool' => $plan->pointsPool(),
            ])->values()->all(),
            'hotels' => $hotels->map(fn (Hotel $hotel): array => [
                'id' => $hotel->id,
                'name' => $hotel->name,
                'city' => $hotel->city,
                'planId' => $hotel->subscription_plan_id,
                'planName' => $hotel->subscriptionPlan?->name,
                'usedEmployees' => $hotel->active_employees_count ?? $hotel->usedSeats(),
                'employeeLimit' => $hotel->subscriptionPlan->employee_limit ?? 0,
            ])->values()->all(),
            'activePlans' => $plans->where('is_active', true)->map(fn (SubscriptionPlan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'employeeLimit' => $plan->employee_limit,
                'priceDzd' => $plan->price_dzd,
                'priceUsd' => $plan->price_usd,
            ])->values()->all(),
            'paymentMethods' => SubscriptionPaymentMethod::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (SubscriptionPaymentMethod $method): array => [
                    'id' => $method->id,
                    'name' => $method->name,
                    'recipientName' => $method->recipient_name,
                    'accountReference' => $method->account_reference,
                    'instructions' => $method->instructions,
                    'sortOrder' => $method->sort_order,
                    'isActive' => $method->is_active,
                ])->values()->all(),
        ]);
    }

    public function updatePlan(UpdateSubscriptionPlanRequest $request, SubscriptionPlan $plan, SubscriptionService $subscriptions): RedirectResponse
    {
        $subscriptions->updatePlan($plan, $request->planData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':plan plan settings were saved.', ['plan' => $plan->name]),
        ]);

        return back();
    }

    public function assignPlan(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $data = $request->validate([
            'hotel_id' => ['required', 'integer', 'exists:hotels,id'],
            'plan_id' => ['required', 'integer', Rule::exists('subscription_plans', 'id')->where('is_active', true)],
        ]);
        $hotel = Hotel::query()->withoutGlobalScopes()->findOrFail((int) $data['hotel_id']);
        $plan = SubscriptionPlan::query()->findOrFail((int) $data['plan_id']);
        $subscriptions->assignPlan($hotel, $plan);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':hotel is now on the :plan plan.', ['hotel' => $hotel->name, 'plan' => $plan->name]),
        ]);

        return back();
    }

    public function recordAiPointPayment(Request $request, HotelAiPointTopUpService $topUps): RedirectResponse
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        abort_unless($request->user('web')?->hasRole(Role::SuperAdmin->value), 403);

        $data = $request->validate([
            'hotel_id' => ['required', 'integer', 'exists:hotels,id'],
            'points' => ['required', 'integer', 'min:1', 'max:100000000'],
            'currency' => ['sometimes', Rule::in(['DZD', 'USD'])],
            'amount_dzd' => ['exclude_if:currency,USD', 'required', 'integer', 'min:1', 'max:1000000000'],
            'amount_usd' => ['exclude_unless:currency,USD', 'required', 'numeric', 'min:0.01', 'max:100000000', 'decimal:0,2'],
            'payment_method_id' => ['nullable', 'integer', 'exists:subscription_payment_methods,id'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'payment_received' => ['accepted'],
        ]);

        $hotel = Hotel::query()
            ->withoutGlobalScopes()
            ->notArchived()
            ->findOrFail((int) $data['hotel_id']);

        $actor = $request->user('web');
        $topUps->recordPayment(
            $hotel,
            (int) $data['points'],
            ($data['currency'] ?? 'DZD') === 'USD'
                ? ['currency' => 'USD', 'amount' => round((float) $data['amount_usd'], 2)]
                : ['currency' => 'DZD', 'amount' => (int) $data['amount_dzd']],
            isset($data['payment_method_id']) ? (int) $data['payment_method_id'] : null,
            $data['payment_reference'] ?? null,
            $actor,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Payment recorded and :points AI points added for :hotel this month.', [
                'points' => number_format((int) $data['points']),
                'hotel' => $hotel->name,
            ]),
        ]);

        return back();
    }

    public function storePaymentMethod(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $data = $this->validatePaymentMethod($request);
        $data['sort_order'] ??= ((int) SubscriptionPaymentMethod::query()->max('sort_order')) + 1;

        DB::transaction(function () use ($data): void {
            $method = new SubscriptionPaymentMethod($data);
            $method->save();
            AuditLog::record($method, 'subscription.payment_method_created', [
                'created' => $method->only(['name', 'recipient_name', 'account_reference', 'instructions', 'sort_order', 'is_active']),
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment method added.')]);

        return back();
    }

    public function updatePaymentMethod(Request $request, SubscriptionPaymentMethod $paymentMethod): RedirectResponse
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $data = $this->validatePaymentMethod($request);
        $paymentMethod->fill($data);

        DB::transaction(function () use ($paymentMethod): void {
            AuditLog::record($paymentMethod, 'subscription.payment_method_updated');
            $paymentMethod->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment method settings saved.')]);

        return back();
    }

    /** @return array{name: string, recipient_name: ?string, account_reference: ?string, instructions: ?string, sort_order?: int, is_active: bool} */
    private function validatePaymentMethod(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'recipient_name' => ['nullable', 'string', 'max:120'],
            'account_reference' => ['nullable', 'required_if:is_active,true', 'string', 'max:180'],
            'instructions' => ['nullable', 'string', 'max:1200'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}

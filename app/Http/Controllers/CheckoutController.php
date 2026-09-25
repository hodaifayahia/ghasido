<?php

namespace App\Http\Controllers;

use App\Http\Requests\HotelSignupRequest;
use App\Models\SubscriptionPaymentMethod;
use App\Models\SubscriptionPlan;
use App\Services\Hotels\HotelService;
use App\Services\Landing\LandingPageContentStore;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public plan selection keeps the hotel and its first manager inactive until
 * Super Admin approval (AUTH-02, AUTH-08, SUB-01; user request 2026-09-23).
 */
final class CheckoutController extends Controller
{
    public function show(
        SubscriptionPlan $plan,
        LandingPageContentStore $content,
    ): Response {
        abort_unless($plan->is_active, 404);

        return Inertia::render('Checkout', [
            'content' => $content->current(),
            'plan' => $this->planData($plan),
            'paymentMethods' => $this->paymentMethods(),
        ]);
    }

    public function store(
        HotelSignupRequest $request,
        SubscriptionPlan $plan,
        HotelService $hotels,
    ): RedirectResponse {
        abort_unless($plan->is_active, 404);

        $hotels->requestAccess(
            [...$request->hotelData(), 'subscription_plan_id' => $plan->id],
            $request->managerData(),
        );

        return to_route('login')->with('status', __('Your :plan plan request has been received. We will contact you to confirm payment and activate your manager account.', [
            'plan' => $plan->name,
        ]));
    }

    /** @return array{id: int, name: string, slug: string, employeeLimit: int, priceDzd: int, pointsPool: int} */
    private function planData(SubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'employeeLimit' => $plan->employee_limit,
            'priceDzd' => $plan->price_dzd,
            'pointsPool' => $plan->pointsPool(),
        ];
    }

    /** @return list<array{id: int, name: string, recipientName: ?string, accountReference: string, instructions: ?string}> */
    private function paymentMethods(): array
    {
        return SubscriptionPaymentMethod::query()
            ->where('is_active', true)
            ->whereNotNull('account_reference')
            ->whereRaw("trim(account_reference) <> ''")
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (SubscriptionPaymentMethod $method): array => [
                'id' => $method->id,
                'name' => $method->name,
                'recipientName' => $method->recipient_name,
                'accountReference' => (string) $method->account_reference,
                'instructions' => $method->instructions,
            ])->values()->all();
    }
}

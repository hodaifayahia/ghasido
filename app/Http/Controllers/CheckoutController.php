<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\PaymentSubmission;
use App\Models\SubscriptionPaymentMethod;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Hotels\HotelService;
use App\Services\Landing\LandingPageContentStore;
use App\Services\Meaning\HelperLanguages;
use App\Services\Meaning\RequestedHelperLanguages;
use App\Services\Payments\PaymentNotifier;
use App\Services\Payments\PaymentSubmissionService;
use App\Services\Subscriptions\IndividualSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Buying a plan online (AUTH-02, AUTH-08, SUB-01; user requests 2026-09-23
 * and 2026-09-27). A hotel plan opens a pending hotel and its inactive first
 * manager; an individual plan an inactive learner with a pending
 * subscription. Either way the customer names the payment method, uploads
 * the receipt or types the transaction reference, and waits for the Super
 * Admin to confirm the payment.
 */
final class CheckoutController extends Controller
{
    public function show(
        Request $request,
        SubscriptionPlan $plan,
        LandingPageContentStore $content,
    ): Response {
        abort_unless($plan->is_active, 404);

        return Inertia::render('Checkout', [
            'content' => $content->current(),
            'plan' => $this->planData($plan),
            'paymentMethods' => $this->paymentMethods(),
            // Where an individual learner studies (the shared catalogue).
            'departments' => $plan->isIndividual()
                ? Department::query()
                    ->whereNull('hotel_id')
                    ->where('is_active', true)
                    ->orderBy('position')
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Department $department): array => ['value' => $department->id, 'label' => $department->name])
                    ->values()
                    ->all()
                : [],
            // "Which languages would help you?" (client request 2026-10-01).
            'helperLanguages' => app(HelperLanguages::class)->options(),
            'proofTypes' => CheckoutRequest::PROOF_TYPES,
            'proofMaxKb' => CheckoutRequest::proofMaxKb(),
            // DZD for Algeria, USD for international customers (client
            // decision 2026-09-26), as chosen on the pricing switch.
            'region' => $request->query('region') === 'intl' ? 'intl' : 'dz',
        ]);
    }

    public function store(
        CheckoutRequest $request,
        SubscriptionPlan $plan,
        HotelService $hotels,
        IndividualSubscriptionService $individuals,
        PaymentSubmissionService $payments,
        PaymentNotifier $notifier,
        RequestedHelperLanguages $helperLanguages,
    ): RedirectResponse {
        abort_unless($plan->is_active, 404);

        $method = $request->paymentMethod();

        $submission = DB::transaction(function () use ($request, $plan, $hotels, $individuals, $payments, $method, $helperLanguages): PaymentSubmission {
            $account = $plan->isIndividual()
                ? $individuals->requestAccess($request->individualData(), $plan, $request->departmentId(), $request->currency())
                : $hotels->requestAccess(
                    [...$request->hotelData(), 'subscription_plan_id' => $plan->id],
                    $request->managerData(),
                    $request->input('region') === 'intl' ? 'intl' : 'dz',
                );

            // The helper languages asked for, on the account that asked
            // (client request 2026-10-01).
            $requester = $account instanceof Hotel
                ? User::query()->where('hotel_id', $account->id)->orderBy('id')->first()
                : $account->user;

            if ($requester !== null) {
                $helperLanguages->apply($requester, $request->requestedHelperLanguages());
            }

            // Always recorded: the receipt and/or reference, and the method
            // when one is set up (null before any is).
            return $payments->submit($account, $plan, $method, $request->paymentData(), $request->proof());
        });

        DB::afterCommit(fn () => $notifier->submitted($submission));

        return to_route('checkout.submitted', ['submission' => $submission->public_id]);
    }

    /**
     * "Payment submitted": what was sent and that it waits for approval. The
     * link is an unguessable id, so it can be bookmarked without signing in.
     */
    public function submitted(string $submission, LandingPageContentStore $content): Response
    {
        $payment = PaymentSubmission::query()->where('public_id', $submission)->firstOrFail();

        return Inertia::render('CheckoutSubmitted', [
            'content' => $content->current(),
            'payment' => [
                'planName' => $payment->plan_name,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'method' => $payment->methodLabel(),
                'reference' => $payment->reference,
                'hasReceipt' => $payment->proof_path !== null,
                'submittedAt' => $payment->created_at?->toIso8601String(),
                'status' => $payment->status->value,
                'statusLabel' => $payment->status->label(),
                'isIndividual' => $payment->isForIndividual(),
            ],
        ]);
    }

    /** @return array{id: int, name: string, slug: string, audience: string, employeeLimit: int, priceDzd: int, priceUsd: float, pointsPool: int} */
    private function planData(SubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'audience' => $plan->audience->value,
            'employeeLimit' => $plan->employee_limit,
            'priceDzd' => $plan->price_dzd,
            'priceUsd' => $plan->price_usd,
            'pointsPool' => $plan->pointsPool(),
        ];
    }

    /** @return list<array{id: int, name: string, recipientName: ?string, accountReference: string, instructions: ?string}> */
    private function paymentMethods(): array
    {
        return array_values(SubscriptionPaymentMethod::query()
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
            ])->all());
    }
}

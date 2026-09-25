<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPaymentMethod;
use App\Models\SubscriptionPlan;
use App\Services\Landing\LandingPageContentStore;
use Inertia\Inertia;
use Inertia\Response;

final class LandingPageController extends Controller
{
    public function __invoke(LandingPageContentStore $content): Response
    {
        return Inertia::render('Welcome', [
            'content' => $content->current(),
            'plans' => SubscriptionPlan::query()
                ->active()
                ->orderBy('employee_limit')
                ->orderBy('id')
                ->get()
                ->map(fn (SubscriptionPlan $plan): array => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'employeeLimit' => $plan->employee_limit,
                    'priceDzd' => $plan->price_dzd,
                    'pointsPool' => $plan->pointsPool(),
                ])->values()->all(),
            'paymentMethods' => SubscriptionPaymentMethod::query()
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
                    'accountReference' => $method->account_reference,
                    'instructions' => $method->instructions,
                ])->values()->all(),
        ]);
    }
}

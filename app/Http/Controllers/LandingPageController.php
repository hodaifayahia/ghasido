<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPaymentMethod;
use App\Models\SubscriptionPlan;
use App\Services\Landing\LandingPageContentStore;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

final class LandingPageController extends Controller
{
    public function __invoke(LandingPageContentStore $content): Response
    {
        return Inertia::render('Welcome', [
            'content' => $content->current(),
            'plans' => $this->plans(SubscriptionPlan::query()->active()->forHotels()->orderBy('employee_limit')),
            // One learner, monthly AI points and no seats (client request
            // 2026-09-27).
            'individualPlans' => $this->plans(SubscriptionPlan::query()->active()->forIndividuals()->orderBy('price_dzd')),
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

    /**
     * @param  Builder<SubscriptionPlan>  $query
     * @return list<array{id: int, name: string, slug: string, employeeLimit: int, priceDzd: int, priceUsd: float, pointsPool: int}>
     */
    private function plans(Builder $query): array
    {
        return array_values($query->orderBy('id')->get()->map(fn (SubscriptionPlan $plan): array => [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'employeeLimit' => $plan->employee_limit,
            'priceDzd' => $plan->price_dzd,
            'priceUsd' => $plan->price_usd,
            'pointsPool' => $plan->pointsPool(),
        ])->all());
    }
}

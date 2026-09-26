<?php

namespace App\Http\Requests\Admin\Subscriptions;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SubscriptionsManage->value) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $plan = $this->route('plan');

        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('subscription_plans', 'name')->ignore($plan?->id)],
            'employee_limit' => ['required', 'integer', 'min:1', 'max:10000'],
            'price_dzd' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'price_usd' => ['required', 'numeric', 'min:0', 'max:100000000', 'decimal:0,2'],
            'points_per_employee' => ['required', 'integer', 'min:0', 'max:100000000'],
            'bonus_points_per_employee' => ['required', 'integer', 'min:0', 'max:100000000'],
            'voice_points_per_10_minutes' => ['required', 'integer', 'min:0', 'max:100000000'],
            'ai_action_points' => ['required', 'integer', 'min:0', 'max:100000000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array{name: string, slug: string, employee_limit: int, price_dzd: int, price_usd: float, points_per_employee: int, bonus_points_per_employee: int, voice_points_per_10_minutes: int, ai_action_points: int, is_active: bool} */
    public function planData(): array
    {
        $data = $this->validated();

        return [
            ...$data,
            'price_usd' => round((float) $data['price_usd'], 2),
            'is_active' => (bool) $data['is_active'],
        ];
    }
}

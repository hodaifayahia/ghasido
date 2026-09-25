<?php

namespace App\Http\Requests\Admin\Hotels;

use App\Models\Hotel;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;

/**
 * Extend Contract: move the end date, never before the start (spec 0002,
 * contracts and seats child, step 3).
 */
class ExtendContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        $hotel = $this->route('hotel');

        return $hotel instanceof Hotel
            && ($this->user()?->can('manageContract', $hotel) ?? false);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $hotel = $this->route('hotel');
        $start = $hotel instanceof Hotel ? $hotel->contract_starts_on : null;

        return [
            'contract_ends_on' => [
                'required',
                'date',
                ...($start === null ? [] : ['after:'.$start->toDateString()]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contract_ends_on.after' => __('The contract end must be after the contract start.'),
        ];
    }

    public function endsOn(): CarbonInterface
    {
        return Date::parse((string) $this->validated('contract_ends_on'));
    }
}

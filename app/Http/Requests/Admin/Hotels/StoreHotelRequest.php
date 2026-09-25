<?php

namespace App\Http\Requests\Admin\Hotels;

use App\Concerns\HotelRules;
use App\Models\Hotel;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create Hotel (spec 0002, AC-12). The state is never taken from the form:
 * HotelService::create() forces pending.
 */
class StoreHotelRequest extends FormRequest
{
    use HotelRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Hotel::class) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return $this->hotelRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->hotelMessages();
    }

    /**
     * @return array{name: string, city: string, manager_name: string, manager_email: string, contract_starts_on: string, contract_ends_on: string}
     */
    public function hotelData(): array
    {
        /** @var array{name: string, city: string, manager_name: string, manager_email: string, contract_starts_on: string, contract_ends_on: string} $data */
        $data = $this->validated();

        return $data;
    }
}

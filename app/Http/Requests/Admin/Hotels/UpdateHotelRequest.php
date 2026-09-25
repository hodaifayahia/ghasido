<?php

namespace App\Http\Requests\Admin\Hotels;

use App\Concerns\HotelRules;
use App\Models\Hotel;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit a hotel's own fields, with the same rules as create (spec 0002,
 * contracts and seats child, step 1).
 */
class UpdateHotelRequest extends FormRequest
{
    use HotelRules;

    public function authorize(): bool
    {
        $hotel = $this->route('hotel');

        return $hotel instanceof Hotel
            && ($this->user()?->can('update', $hotel) ?? false);
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

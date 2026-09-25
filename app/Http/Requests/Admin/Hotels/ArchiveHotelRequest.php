<?php

namespace App\Http\Requests\Admin\Hotels;

use App\Models\Hotel;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Archive a hotel: access stops, every row stays, the reason is optional
 * (spec 0002, contracts and seats child, step 2; DATA-10).
 */
class ArchiveHotelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $hotel = $this->route('hotel');

        return $hotel instanceof Hotel
            && ($this->user()?->can('archive', $hotel) ?? false);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) && trim($reason) !== '' ? $reason : null;
    }
}

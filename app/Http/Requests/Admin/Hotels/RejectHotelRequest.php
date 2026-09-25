<?php

namespace App\Http\Requests\Admin\Hotels;

use App\Models\Hotel;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reject a pending hotel. The reason is required: it is the only thing that
 * later tells a rejection from an old archive (spec 0002, AC-14).
 */
class RejectHotelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $hotel = $this->route('hotel');

        return $hotel instanceof Hotel
            && ($this->user()?->can('approve', $hotel) ?? false);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function reason(): string
    {
        return (string) $this->validated('reason');
    }
}

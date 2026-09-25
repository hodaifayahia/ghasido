<?php

namespace App\Concerns;

/**
 * Shared validation for a hotel's own fields, so create and edit cannot drift
 * apart (spec 0002, AC-12; create and approve child, step 2).
 *
 * `manager_email` is validated as an email but is contact data, not a login,
 * and is deliberately NOT unique: one person may run two hotels.
 */
trait HotelRules
{
    /**
     * @return array<string, list<string>>
     */
    protected function hotelRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'manager_name' => ['required', 'string', 'max:120'],
            'manager_email' => ['required', 'string', 'email', 'max:255'],
            'contract_starts_on' => ['required', 'date'],
            'contract_ends_on' => ['required', 'date', 'after:contract_starts_on'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function hotelMessages(): array
    {
        return [
            'contract_ends_on.after' => __('The contract end must be after the contract start.'),
        ];
    }
}

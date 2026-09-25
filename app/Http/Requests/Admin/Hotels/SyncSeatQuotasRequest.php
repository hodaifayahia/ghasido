<?php

namespace App\Http\Requests\Admin\Hotels;

use App\Models\Department;
use App\Models\Hotel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Manage seats: the full set of department quotas for one hotel (SUB-01;
 * spec 0002, AC-6, AC-7).
 *
 * A value below current usage is deliberately accepted: lowering only refuses
 * the next account, it never touches an existing one (SUB-03).
 */
class SyncSeatQuotasRequest extends FormRequest
{
    public function authorize(): bool
    {
        $hotel = $this->route('hotel');

        return $hotel instanceof Hotel
            && ($this->user()?->can('manageSeats', $hotel) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $hotel = $this->route('hotel');

        // Only departments this hotel may draw on: the shared catalogue plus
        // its own. Anything else is a 422, never a silent row.
        $department = Rule::exists(Department::class, 'id')
            ->where(function ($query) use ($hotel): void {
                $query->whereNull('hotel_id');

                if ($hotel instanceof Hotel) {
                    $query->orWhere('hotel_id', $hotel->id);
                }
            });

        return [
            'quotas' => ['required', 'array'],
            'quotas.*.department_id' => ['required', 'integer', 'distinct', $department],
            'quotas.*.allowed_seats' => ['required', 'integer', 'min:0', 'max:10000'],
        ];
    }

    /**
     * @return list<array{department_id: int, allowed_seats: int}>
     */
    public function quotas(): array
    {
        /** @var list<array{department_id: int|string, allowed_seats: int|string}> $rows */
        $rows = $this->validated('quotas');

        return array_map(
            static fn (array $row): array => [
                'department_id' => (int) $row['department_id'],
                'allowed_seats' => (int) $row['allowed_seats'],
            ],
            $rows,
        );
    }
}

<?php

namespace App\Http\Requests\Admin\Hotels;

use App\Concerns\DepartmentRules;
use App\Models\Department;
use App\Models\Hotel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Hotel row action "Departments": give the hotel a department with a seat
 * quota (SUB-01, ORG-02). Either an existing department from the hotel's
 * catalogue (shared, or its own) or a new one created for this hotel.
 */
class AddHotelDepartmentRequest extends FormRequest
{
    use DepartmentRules;

    public function authorize(): bool
    {
        $hotel = $this->route('hotel');

        return $hotel instanceof Hotel
            && ($this->user('web')?->can('manageSeats', $hotel) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name', ''));

        $this->merge([
            'name' => $name === '' ? null : $name,
            'slug' => $name === '' ? null : Str::slug($name),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $hotel = $this->route('hotel');
        $hotelId = $hotel instanceof Hotel ? $hotel->id : 0;

        $department = Rule::exists(Department::class, 'id')
            ->where('is_active', true)
            ->where(function ($query) use ($hotelId): void {
                $query->whereNull('hotel_id')->orWhere('hotel_id', $hotelId);
            });

        return [
            'department_id' => ['nullable', 'integer', 'required_without:name', 'prohibits:name', $department],
            'name' => ['nullable', 'string', 'max:120', 'required_without:department_id'],
            'slug' => ['nullable', 'string', 'max:120', $this->departmentSlugRule($hotelId)],
            'allowed_seats' => ['required', 'integer', 'min:0', 'max:10000'],
        ];
    }

    /**
     * A new department is a department write too: it needs the Departments
     * capability, not only Hotels (ROLE-01).
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('name') && ! ($this->user('web')?->can('create', Department::class) ?? false)) {
                    $validator->errors()->add('name', __('You may not create departments. Pick one from the list.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => __('This hotel already has a department with that name.'),
            'department_id.required_without' => __('Pick a department, or type a name for a new one.'),
            'department_id.exists' => __('That department is not available to this hotel.'),
        ];
    }

    public function department(): ?Department
    {
        $id = $this->validated('department_id');

        return $id === null ? null : Department::query()->findOrFail((int) $id);
    }

    public function newName(): ?string
    {
        $name = $this->validated('name');

        return is_string($name) ? $name : null;
    }

    public function seats(): int
    {
        return (int) $this->validated('allowed_seats');
    }
}

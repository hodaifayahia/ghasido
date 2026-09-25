<?php

namespace App\Http\Requests\Admin\Departments;

use App\Concerns\DepartmentRules;
use App\Enums\DepartmentStatus;
use App\Enums\Role;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Add Department (spec 0003 Part D). The scope is `shared` (the catalogue
 * every hotel draws on, ORG-04) or `hotel` with the owning hotel's id.
 *
 * Only the Super Admin may add to the shared catalogue, and anyone else may
 * only add to their own hotel: both are validation rules here, on top of the
 * policy's capability check (ROLE-02).
 */
class StoreDepartmentRequest extends FormRequest
{
    use DepartmentRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Department::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) $this->input('name', '')),
            'scope' => (string) $this->input('scope', 'shared'),
            'hotel_id' => $this->input('scope') === 'hotel' ? $this->input('hotel_id') : null,
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $user = $this->user();
        $superAdmin = $user?->hasRole(Role::SuperAdmin->value) ?? false;
        $hotelId = $this->hotelId();

        $ownHotel = $user instanceof User ? ($user->hotel_id ?? 0) : 0;

        $hotelRule = $superAdmin
            ? Rule::exists(Hotel::class, 'id')
            : Rule::in([$ownHotel]);

        return [
            ...$this->departmentFieldRules(),
            'scope' => ['required', Rule::in($superAdmin ? ['shared', 'hotel'] : ['hotel'])],
            'hotel_id' => ['nullable', 'integer', 'required_if:scope,hotel', $hotelRule],
            'slug' => ['nullable', 'string', 'max:120', $this->departmentSlugRule($hotelId)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->departmentMessages(),
            'scope.in' => __('A hotel manager can only add departments to their own hotel.'),
            'hotel_id.in' => __('A hotel manager can only add departments to their own hotel.'),
        ];
    }

    /**
     * @return array{name: string, slug: string, focus: ?string, status: DepartmentStatus, hotel_id: ?int}
     */
    public function departmentData(): array
    {
        /** @var array{name: string, slug: ?string, focus?: ?string, status: string, hotel_id: ?int} $data */
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'slug' => $data['slug'] ?? '',
            'focus' => $data['focus'] ?? null,
            'status' => DepartmentStatus::from($data['status']),
            'hotel_id' => $this->hotelId(),
        ];
    }

    private function hotelId(): ?int
    {
        $hotelId = $this->input('hotel_id');

        return is_numeric($hotelId) ? (int) $hotelId : null;
    }
}

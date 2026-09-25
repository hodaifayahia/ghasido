<?php

namespace App\Http\Requests\Admin\Roles;

use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Rename a custom role and set which permissions it holds (client request
 * 2026-09-23).
 *
 * The controller ignores `name` for a system role and forces every
 * permission on super_admin, so this request only has to keep the input
 * well-formed (spec 0001, invariants 3 and 5).
 */
class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::RolesManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = $this->route('role');
        $roleId = $role instanceof Role ? $role->getKey() : null;

        return [
            'name' => [
                'sometimes',
                'string',
                'max:60',
                'regex:/^[A-Za-z0-9 _-]+$/',
                Rule::notIn(array_column(RoleEnum::cases(), 'value')),
                Rule::unique('roles', 'name')->ignore($roleId),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Permission::names())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => __('Use only letters, numbers, spaces, hyphens and underscores.'),
            'name.not_in' => __('That name is reserved for a built-in role.'),
        ];
    }
}

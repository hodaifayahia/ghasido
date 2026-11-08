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
        $user = $this->user('web');
        $role = $this->route('role');

        if ($user === null || ! $role instanceof Role || ! $user->can(Permission::RolesManage->value)) {
            return false;
        }

        if ($user->hasRole(RoleEnum::SuperAdmin->value)) {
            return true;
        }

        // Security audit 2026-11-08: a delegate holding roles.manage may not
        // touch the Super Admin role or their own role, may not edit a role
        // that already holds more than they do, and may only grant
        // permissions they hold themselves (ROLE-01).
        if ($role->name === RoleEnum::SuperAdmin->value || $user->hasRole($role->name)) {
            return false;
        }

        // Roles are platform-wide: a hotel-bound delegate edits none of
        // them, and no delegate edits a system role, whose holders sit in
        // every hotel (ROLE-02; security audit pass 3).
        if ($user->hotel_id !== null || RoleEnum::isSystem($role->name)) {
            return false;
        }

        $held = $user->permissionNames();

        if ($role->permissions()->whereNotIn('name', $held)->exists()) {
            return false;
        }

        $permissions = $this->input('permissions', []);

        return ! is_array($permissions) || array_diff(array_filter($permissions, 'is_string'), $held) === [];
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
        $builtIn = array_column(RoleEnum::cases(), 'value');

        // A built-in role keeps its name and the controller ignores the
        // field, so the form sending it back unchanged ("manager") must not
        // fail as "reserved" (client report 2026-10-02: the Hotel Manager's
        // permissions could not be saved).
        if ($role instanceof Role && in_array($role->name, $builtIn, true)) {
            return [
                'name' => ['sometimes', 'nullable', 'string'],
                'permissions' => ['array'],
                'permissions.*' => ['string', Rule::in(Permission::names())],
            ];
        }

        return [
            'name' => [
                'sometimes',
                'string',
                'max:60',
                'regex:/^[A-Za-z0-9 _-]+$/',
                Rule::notIn($builtIn),
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

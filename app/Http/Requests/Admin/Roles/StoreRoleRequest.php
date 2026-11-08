<?php

namespace App\Http\Requests\Admin\Roles;

use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a custom role (client request 2026-09-23).
 *
 * `permissions` is validated against the fixed catalogue, so a role can only
 * ever hold capabilities the code actually checks (spec 0001, invariant 5).
 * The name may not collide with a system role.
 *
 * @property-read string $name
 */
class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('web');

        if ($user === null || ! $user->can(Permission::RolesManage->value)) {
            return false;
        }

        // Security audit 2026-11-08: below the Super Admin, a role can only
        // be given permissions the actor holds themselves (ROLE-01).
        return $user->hasRole(RoleEnum::SuperAdmin->value)
            || $this->withinCeiling($user, $this->input('permissions', []));
    }

    /**
     * Whether every requested permission is one the actor already holds.
     */
    private function withinCeiling(User $user, mixed $permissions): bool
    {
        if (! is_array($permissions)) {
            return true; // Malformed input fails validation instead.
        }

        $requested = array_filter($permissions, 'is_string');

        return array_diff($requested, $user->permissionNames()) === [];
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
        return [
            'name' => [
                'required',
                'string',
                'max:60',
                'regex:/^[A-Za-z0-9 _-]+$/',
                Rule::notIn(array_column(RoleEnum::cases(), 'value')),
                Rule::unique('roles', 'name'),
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

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Roles\StoreRoleRequest;
use App\Http\Requests\Admin\Roles\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Roles & Permissions screen (ROLE-01; spec 0001, client request
 * 2026-09-23: the Super Admin manages custom roles and each role's
 * permissions from the panel).
 *
 * Read → authorize → delegate. The route already carries
 * permission:roles.view / permission:roles.manage; the two invariants this
 * screen owns — the four system roles cannot be deleted, and super_admin
 * always holds every permission — are enforced here so no crafted request
 * can cripple the platform owner or remove a fixed role (spec 0001,
 * invariants 1 and 3).
 */
class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Roles', [
            'roles' => $this->roles(),
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $role = Role::create(['name' => $data['name']]);
        $role->syncPermissions($this->allowedPermissions($data['permissions'] ?? []));

        $this->forgetCache();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':role was created.', ['role' => $data['name']]),
        ]);

        return to_route('roles');
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $data = $request->validated();

        // super_admin is locked to every permission (spec 0001, invariant 3):
        // whatever the request sent, the platform owner keeps them all.
        if ($role->name === RoleEnum::SuperAdmin->value) {
            $role->syncPermissions(PermissionEnum::names());
        } else {
            $role->syncPermissions($this->allowedPermissions($data['permissions'] ?? []));
        }

        // Only a custom role may be renamed; the four system role names are
        // read across the codebase, so they never change.
        if (! RoleEnum::isSystem($role->name) && isset($data['name']) && $data['name'] !== $role->name) {
            $role->name = $data['name'];
            $role->save();
        }

        $this->forgetCache();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('The :role role was updated.', ['role' => $role->name]),
        ]);

        return back();
    }

    public function destroy(Role $role): RedirectResponse
    {
        // A system role is never deletable, even for the Super Admin.
        abort_if(RoleEnum::isSystem($role->name), 403, __('System roles cannot be deleted.'));

        if ($role->users()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Reassign the accounts that hold this role before deleting it.'),
            ]);

            return back();
        }

        $name = $role->name;
        $role->delete();

        $this->forgetCache();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('The :role role was deleted.', ['role' => $name]),
        ]);

        return to_route('roles');
    }

    /**
     * Every role with its permission names and how many accounts hold it.
     *
     * @return list<array<string, mixed>>
     */
    private function roles(): array
    {
        return array_values(
            Role::query()
                ->with('permissions')
                ->withCount('users')
                ->orderBy('id')
                ->get()
                ->map(function (Role $role): array {
                    $name = (string) $role->name;

                    return [
                        'id' => $role->getKey(),
                        'name' => $name,
                        'label' => RoleEnum::tryFrom($name)?->label() ?? Str::headline($name),
                        'isSystem' => RoleEnum::isSystem($name),
                        'locked' => $name === RoleEnum::SuperAdmin->value,
                        'userCount' => (int) $role->getAttribute('users_count'),
                        'permissions' => $role->permissions->pluck('name')->all(),
                    ];
                })
                ->all(),
        );
    }

    /**
     * The permission catalogue, grouped by the screen each belongs to, for
     * the checkbox matrix.
     *
     * @return list<array{group: string, permissions: list<array{name: string, label: string}>}>
     */
    private function permissionGroups(): array
    {
        /** @var array<string, list<array{name: string, label: string}>> $grouped */
        $grouped = [];

        foreach (PermissionEnum::cases() as $permission) {
            $grouped[$permission->group()][] = [
                'name' => $permission->value,
                'label' => $permission->label(),
            ];
        }

        $groups = [];

        foreach ($grouped as $group => $permissions) {
            $groups[] = ['group' => $group, 'permissions' => $permissions];
        }

        return $groups;
    }

    /**
     * Keep only real permission names, so a crafted request can never grant a
     * capability the code does not define (spec 0001, invariant 5).
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function allowedPermissions(array $names): array
    {
        return array_values(array_intersect($names, PermissionEnum::names()));
    }

    private function forgetCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\StoreUserRequest;
use App\Http\Requests\Admin\Users\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** The platform's own back-office users; hotel managers and learners are managed per hotel. */
class UsersController extends Controller
{
    public function index(): Response
    {
        Gate::authorize(Permission::UsersView->value);
        /** @var User $actor */
        $actor = request()->user();

        $accounts = User::query()
            ->with('roles.permissions')
            // Only the people who run the platform (client request
            // 2026-09-29): super admins, admins and custom back-office
            // roles. Hotel managers and learners are managed from the hotel.
            ->whereNull('hotel_id')
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('name', [RoleEnum::Employee->value, RoleEnum::Manager->value]))
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $this->roleRecord($user->roles->first()),
                'status' => $user->status->value,
                'isCurrentUser' => $user->is(auth()->user()),
            ]);

        return Inertia::render('admin/Users', [
            'accounts' => $accounts,
            'roles' => Role::query()
                ->with('permissions')
                ->withCount('permissions')
                ->whereNotIn('name', [RoleEnum::Employee->value, RoleEnum::Manager->value])
                ->orderBy('id')
                ->get()
                ->filter(fn (Role $role): bool => $actor->can(Permission::UsersManage->value)
                    && $this->canAssignRole($actor, $role))
                ->values()
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'label' => RoleEnum::tryFrom($role->name)?->label() ?? Str::headline($role->name),
                    'permissionCount' => (int) $role->permissions_count,
                ])
                ->all(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        Gate::authorize(Permission::UsersManage->value);
        $data = $request->accountData();
        $role = Role::query()->findOrFail((int) $data['role_id']);
        $this->assertAssignableRole($request->user('web'), $role);

        $account = DB::transaction(function () use ($request, $data, $role): User {
            $user = new User;
            $user->fill([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => AccountStatus::Active,
                'created_by' => $request->user()->id,
            ]);
            $user->save();
            $user->syncRoles([$role->name]);

            // Passwords never enter the audit log (AUTH-03, SEC-06, ADM-03).
            AuditLog::record($user, 'user.created', [
                'created' => $user->only(['name', 'username', 'email', 'status']),
                'role' => $role->name,
            ]);

            return $user;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name was added to app users.', ['name' => $account->name]),
        ]);

        return to_route('users');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize(Permission::UsersManage->value);
        // Hotel managers and learners belong to their hotel, not this page.
        abort_if($user->hotel_id !== null || $user->hasAnyRole([RoleEnum::Employee->value, RoleEnum::Manager->value]), 404);
        abort_if(
            $user->hasRole(RoleEnum::SuperAdmin->value)
                && ! $request->user('web')?->hasRole(RoleEnum::SuperAdmin->value),
            403,
            __('Only a Super Admin can change another Super Admin account.'),
        );

        $data = $request->accountChanges();
        $role = Role::query()->findOrFail((int) $data['role_id']);
        $this->assertAssignableRole($request->user('web'), $role);

        if ($user->is($request->user()) && $data['status'] !== AccountStatus::Active->value) {
            abort(422, __('You cannot deactivate your own account.'));
        }

        $wasLastActiveSuperAdmin = $user->hasRole(RoleEnum::SuperAdmin->value)
            && $user->status === AccountStatus::Active
            && ($role->name !== RoleEnum::SuperAdmin->value || $data['status'] !== AccountStatus::Active->value)
            && User::query()->whereHas('roles', fn ($query) => $query->where('name', RoleEnum::SuperAdmin->value))
                ->where('status', AccountStatus::Active->value)
                ->count() <= 1;

        abort_if($wasLastActiveSuperAdmin, 422, __('Keep at least one active Super Admin account.'));

        DB::transaction(function () use ($user, $data, $role): void {
            $oldRole = $user->roles()->value('name');
            $user->fill([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'status' => AccountStatus::from($data['status']),
            ]);
            $user->syncRoles([$role->name]);
            AuditLog::record($user, 'user.updated', [
                'role' => ['from' => $oldRole, 'to' => $role->name],
                ...($data['password'] === null ? [] : ['password' => 'changed']),
            ]);

            // Set the password after the diff has been recorded so neither
            // the clear value nor its hash can enter the audit row (SEC-06).
            if ($data['password'] !== null) {
                $user->password = $data['password'];
            }

            $user->save();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name was updated.', ['name' => $user->name]),
        ]);

        return to_route('users');
    }

    /**
     * Delegated managers can only assign a role no more powerful than their
     * own. The Super Admin may grant any role, including full platform access.
     */
    private function assertAssignableRole(?User $actor, Role $role): void
    {
        abort_if($role->name === RoleEnum::Employee->value, 422, __('Hotel employees are managed on the Employees page.'));
        abort_if($role->name === RoleEnum::Manager->value, 422, __('Hotel managers are managed on their hotel’s page.'));

        abort_unless($actor !== null && $this->canAssignRole($actor, $role), 403, __('You cannot grant access beyond your own permissions.'));
    }

    private function canAssignRole(User $actor, Role $role): bool
    {
        if ($actor->hasRole(RoleEnum::SuperAdmin->value)) {
            return true;
        }

        if ($role->name === RoleEnum::SuperAdmin->value || $role->name === RoleEnum::Employee->value) {
            return false;
        }

        $held = $actor->getAllPermissions()->pluck('name');

        return $role->permissions->pluck('name')->diff($held)->isEmpty();
    }

    /** @return array{id: int, name: string, label: string, permissionCount: int}|null */
    private function roleRecord(?Model $role): ?array
    {
        if (! $role instanceof Role) {
            return null;
        }

        return [
            'id' => (int) $role->id,
            'name' => $role->name,
            'label' => RoleEnum::tryFrom($role->name)?->label() ?? Str::headline($role->name),
            'permissionCount' => $role->permissions->count(),
        ];
    }
}

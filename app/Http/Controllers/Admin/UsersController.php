<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\StoreUserRequest;
use App\Http\Requests\Admin\Users\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
            ->with(['roles.permissions', 'hotel'])
            // The people who run the platform or a hotel's back office
            // (client requests 2026-09-29, 2026-10-02): super admins, hotel
            // admins and custom roles. Hotel managers and learners are
            // managed from the hotel. A hotel-bound actor sees their hotel's.
            ->when($actor->hotel_id !== null, fn ($query) => $query->where('hotel_id', $actor->hotel_id))
            ->whereNull('removed_at')
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('name', [RoleEnum::Employee->value, RoleEnum::Manager->value]))
            // …and holding a back-office role: an account with no role at
            // all has no access to run anything.
            ->whereHas('roles', fn ($query) => $query->whereNotIn('name', [RoleEnum::Employee->value, RoleEnum::Manager->value]))
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $this->roleRecord($user->roles->first()),
                'hotelId' => $user->hotel_id,
                'hotelName' => $user->hotel?->name,
                'status' => $user->status->value,
                'isCurrentUser' => $user->is(auth()->user()),
            ]);

        return Inertia::render('admin/Users', [
            'accounts' => $accounts,
            // Where a Hotel Admin or a hotel-bound custom role works.
            'hotels' => Hotel::query()
                ->notArchived()
                ->when($actor->hotel_id !== null, fn ($query) => $query->whereKey($actor->hotel_id))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Hotel $hotel): array => ['value' => $hotel->id, 'label' => $hotel->name])
                ->all(),
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
        $hotelId = $this->hotelFor($request->user('web'), $role, $data['hotel_id']);

        $account = DB::transaction(function () use ($request, $data, $role, $hotelId): User {
            $user = new User;
            $user->fill([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => AccountStatus::Active,
                'created_by' => $request->user()->id,
            ]);
            $user->hotel_id = $hotelId;
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
        // Hotel managers and learners belong to their hotel, not this page;
        // a hotel-bound actor edits their own hotel's accounts only.
        $actor = $request->user('web');
        abort_if($user->hasAnyRole([RoleEnum::Employee->value, RoleEnum::Manager->value]), 404);
        abort_if($actor->hotel_id !== null && $user->hotel_id !== $actor->hotel_id, 404);
        abort_if(
            $user->hasRole(RoleEnum::SuperAdmin->value)
                && ! $request->user('web')?->hasRole(RoleEnum::SuperAdmin->value),
            403,
            __('Only a Super Admin can change another Super Admin account.'),
        );

        $data = $request->accountChanges();
        $role = Role::query()->findOrFail((int) $data['role_id']);
        $this->assertAssignableRole($request->user('web'), $role);
        $hotelId = $this->hotelFor($request->user('web'), $role, $data['hotel_id']);

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

        DB::transaction(function () use ($user, $data, $role, $hotelId): void {
            $oldRole = $user->roles()->value('name');
            $user->fill([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'status' => AccountStatus::from($data['status']),
            ]);
            $user->hotel_id = $hotelId;
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

    /**
     * The hotel an account works for. A Hotel Admin needs one; the Super
     * Admin works for none; a custom role may be either. A hotel-bound actor
     * can only give their own hotel (ROLE-02).
     */
    private function hotelFor(?User $actor, Role $role, ?int $hotelId): ?int
    {
        if ($role->name === RoleEnum::SuperAdmin->value) {
            return null;
        }

        if ($actor?->hotel_id !== null) {
            return $actor->hotel_id;
        }

        if ($role->name === RoleEnum::Admin->value && $hotelId === null) {
            throw ValidationException::withMessages([
                'hotel_id' => __('Choose the hotel this Hotel Admin works for.'),
            ]);
        }

        return $hotelId;
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

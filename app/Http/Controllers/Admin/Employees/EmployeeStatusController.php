<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Accounts\AccountRemoval;
use App\Services\Employees\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Activate and deactivate one account (AUTH-08, SUB-02, DATA-10; spec 0003
 * Part D, employees.activate / employees.deactivate).
 */
class EmployeeStatusController extends Controller
{
    public function activate(User $employee, EmployeeService $employees): RedirectResponse
    {
        Gate::authorize('activate', $employee);

        $employees->activate($employee);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name is active again.', ['name' => $employee->name]),
        ]);

        return back();
    }

    public function deactivate(User $employee, EmployeeService $employees): RedirectResponse
    {
        Gate::authorize('deactivate', $employee);

        $employees->deactivate($employee);

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => __(':name was deactivated. Their progress and answers are kept.', ['name' => $employee->name]),
        ]);

        return back();
    }

    /**
     * Delete an account: anonymised, its username, email and phone freed,
     * its answers kept for the reports. Super Admin only (client request
     * 2026-10-02; DATA-10).
     */
    public function remove(Request $request, User $employee, AccountRemoval $removal): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user('web');
        abort_unless($actor->hasRole(Role::SuperAdmin->value), 403);
        abort_if($employee->hasRole(Role::SuperAdmin->value) || $employee->is($actor), 403);

        $name = $employee->name;
        $removal->removeUser($employee, $actor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name was deleted. Their username and email are free again; their answers stay in the reports anonymously.', ['name' => $name]),
        ]);

        return back();
    }
}

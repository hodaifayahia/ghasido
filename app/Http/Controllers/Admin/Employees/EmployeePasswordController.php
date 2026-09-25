<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Employees\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Reset an employee's password (AUTH-07; spec 0003 Part D,
 * employees.reset-password).
 *
 * The new password travels once, as Inertia flash data, which is never
 * written into the browser's history state; the page shows it in a dialog
 * and it is gone on the next navigation.
 */
class EmployeePasswordController extends Controller
{
    public function reset(User $employee, EmployeeService $employees): RedirectResponse
    {
        Gate::authorize('resetPassword', $employee);

        $password = $employees->resetPassword($employee);

        Inertia::flash([
            'toast' => [
                'type' => 'success',
                'message' => __('A new password was set for :name.', ['name' => $employee->name]),
            ],
            'employeeCredentials' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'username' => (string) $employee->username,
                'password' => $password,
            ],
        ]);

        return back();
    }
}

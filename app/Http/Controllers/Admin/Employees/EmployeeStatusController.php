<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Employees\EmployeeService;
use Illuminate\Http\RedirectResponse;
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
}

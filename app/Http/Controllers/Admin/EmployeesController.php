<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Employees\StoreEmployeeRequest;
use App\Http\Requests\Admin\Employees\UpdateEmployeeRequest;
use App\Models\User;
use App\Services\Employees\EmployeeDirectory;
use App\Services\Employees\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manage Employees (SUB-02, ORG-03, AUTH-02, AUTH-03, REM-07, REP-03,
 * ADM-02; spec 0003 Part D).
 *
 * Read → authorize → delegate. No query is written here: the directory
 * service reads through the model scopes and a resource, the form requests
 * validate and authorize, and EmployeeService performs every change with its
 * audit row.
 */
class EmployeesController extends Controller
{
    public function index(Request $request, EmployeeDirectory $directory): Response
    {
        // The route already carries permission:employees.view. The policy
        // check is the authorization proper (ROLE-01, SEC-01).
        Gate::authorize('viewAny', User::class);

        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('admin/Employees', $directory->build($request, $actor));
    }

    public function store(StoreEmployeeRequest $request, EmployeeService $employees): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $employee = $employees->create($request->newEmployee(), $actor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name was created. Username: :username.', [
                'name' => $employee->name,
                'username' => (string) $employee->username,
            ]),
        ]);

        return to_route('employees', ['employee' => $employee->id]);
    }

    public function update(UpdateEmployeeRequest $request, User $employee, EmployeeService $employees): RedirectResponse
    {
        $employees->update($employee, $request->changes());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name was updated.', ['name' => $employee->name]),
        ]);

        return back();
    }
}

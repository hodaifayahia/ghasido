<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Departments\StoreDepartmentRequest;
use App\Http\Requests\Admin\Departments\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\Departments\DepartmentDirectory;
use App\Services\Departments\DepartmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The departments screen (ORG-02, ORG-03, ORG-04, SUB-01, JOURNEY-03,
 * CMS-04, TSTM-05, RP-01, REP-01, ADM-02; spec 0003 Part D).
 *
 * Read → authorize → delegate. No query is written here: the directory
 * service reads through model methods and the presenter, the form requests
 * validate, and DepartmentService performs every change with its audit row.
 */
class DepartmentsController extends Controller
{
    public function index(Request $request, DepartmentDirectory $directory): Response
    {
        // The route already carries permission:departments.view. The policy
        // check is the authorization proper, so it holds even if the route's
        // middleware is ever changed (ROLE-01, SEC-01).
        Gate::authorize('viewAny', Department::class);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('admin/Departments', $directory->build($request, $user));
    }

    public function store(StoreDepartmentRequest $request, DepartmentService $departments): RedirectResponse
    {
        $department = $departments->create($request->departmentData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':department was created.', ['department' => $department->name]),
        ]);

        return to_route('departments', ['department' => $department->id]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department, DepartmentService $departments): RedirectResponse
    {
        $departments->update($department, $request->departmentData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':department was updated.', ['department' => $department->name]),
        ]);

        return back();
    }

    public function toggle(Request $request, Department $department, DepartmentService $departments): RedirectResponse
    {
        Gate::authorize('toggle', $department);

        $departments->toggle($department);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $department->is_active
                ? __(':department was restored.', ['department' => $department->name])
                : __(':department was archived. Its employees and content are kept.', ['department' => $department->name]),
        ]);

        return back();
    }
}

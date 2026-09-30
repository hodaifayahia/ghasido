<?php

namespace App\Http\Controllers\Learn;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Learning\TrainingDepartments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The department a manager chooses to train in (client decision 2026-09-23).
 *
 * A manager learns as an employee, but has no department of their own, so
 * they pick one first. The chosen id lives in the session and is validated
 * against TrainingDepartments on the way in and out, so a manager can only
 * ever train in a department they may see (ROLE-02, SEC-01). An employee
 * already has a department and the Super Admin previews without this step, so
 * both are sent straight to the learner home.
 */
class TrainingDepartmentController extends Controller
{
    public function __construct(private readonly TrainingDepartments $departments) {}

    public function edit(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasRole(Role::Manager->value) || $user->department_id !== null) {
            return to_route('learn.home');
        }

        $current = (int) $request->session()->get('training_department_id');

        return Inertia::render('employee/TrainingDepartment', [
            'departments' => $this->departments->options($user),
            'currentDepartmentId' => $current > 0 ? $current : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->hasRole(Role::Manager->value) && $user->department_id === null, 403);

        $available = $this->departments->availableFor($user)->pluck('id')->all();

        $validated = $request->validate([
            'department_id' => ['required', 'integer', Rule::in($available)],
        ]);

        $request->session()->put('training_department_id', (int) $validated['department_id']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('You are now training in this department.'),
        ]);

        return to_route('learn.home');
    }
}

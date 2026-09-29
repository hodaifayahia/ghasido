<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use App\Services\Learning\TrainingDepartments;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves a manager's chosen training department for the learner routes
 * (client decision 2026-09-23).
 *
 * A real employee learns their own department, so they pass straight through.
 * A manager has none: their choice lives in the session, and this middleware
 * validates it against TrainingDepartments (so it must be a department they
 * may actually train in) before putting it on the transient
 * $user->trainingDepartmentId the learner scopes read. Until they have a
 * valid choice they are sent to the department chooser, the same shape as the
 * first-login gate, never looping on the chooser routes themselves.
 */
class ResolveTrainingDepartment
{
    public function __construct(private readonly TrainingDepartments $departments) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // An individual subscriber with several departments studies the one
        // chosen in the session, else their main one (users.department_id);
        // they are never sent to the chooser (owner request 2026-09-25).
        if ($user instanceof User && $user->isIndividual()) {
            $chosen = (int) $request->session()->get('training_department_id', 0);

            if ($chosen > 0 && $chosen !== $user->department_id && $this->departments->canTrainIn($user, $chosen)) {
                $user->trainingDepartmentId = $chosen;
            }

            return $next($request);
        }

        // Employees (a department of their own) and every non-manager pass
        // through untouched, so the Super Admin's employee-preview is
        // unchanged.
        if (! $user instanceof User || $user->department_id !== null || ! $user->hasRole(Role::Manager->value)) {
            return $next($request);
        }

        $chosen = (int) $request->session()->get('training_department_id', 0);

        if ($chosen > 0 && $this->departments->canTrainIn($user, $chosen)) {
            $user->trainingDepartmentId = $chosen;

            return $next($request);
        }

        if ($request->routeIs('learn.training-department.*')) {
            return $next($request);
        }

        return redirect()->route('learn.training-department.edit');
    }
}

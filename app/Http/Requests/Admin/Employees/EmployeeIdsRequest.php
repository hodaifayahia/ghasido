<?php

namespace App\Http\Requests\Admin\Employees;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * A set of employee ids to act on: the checked rows of the directory, or one
 * row's Send reminder button (spec 0003 Part D, employees.bulk and
 * employees.remind).
 *
 * The ids are only validated to be integers here; employees() resolves them
 * and asks the policy about every single one, so a foreign hotel's id in the
 * list is a 403 for the whole request, never a silent skip (ROLE-02).
 */
abstract class EmployeeIdsRequest extends FormRequest
{
    /**
     * The policy ability every listed employee must pass.
     */
    abstract protected function ability(): string;

    public function authorize(): bool
    {
        // A coarse gate: every subclass is a management action (bulk
        // activate/deactivate/remind), so it needs the edit capability, not
        // EmployeesCreate. The per-employee ability() check in employees()
        // is the authorization proper (ROLE-02).
        return $this->user()?->can(Permission::EmployeesManage->value) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            // No `exists` rule: a 422 for an unknown id beside a 403 for a
            // foreign one would let a manager probe which ids exist in other
            // hotels. employees() answers 403 for both (spec 0005 §1.7).
            'ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => __('Select at least one employee first.'),
            'ids.min' => __('Select at least one employee first.'),
        ];
    }

    /**
     * The employees, in the order the ids were given, every one authorized.
     *
     * @return Collection<int, User>
     */
    public function employees(): Collection
    {
        /** @var list<int|string> $ids */
        $ids = $this->validated('ids');
        $ids = array_map('intval', $ids);

        /** @var Collection<int, User> $employees */
        $employees = User::query()
            ->with(['hotel' => fn ($query) => $query->withoutGlobalScopes(), 'department'])
            ->findMany($ids)
            ->sortBy(fn (User $user): int => array_search($user->id, $ids, true) ?: 0)
            ->values();

        if ($employees->count() !== count($ids)) {
            abort(403);
        }

        foreach ($employees as $employee) {
            if (! ($this->user()?->can($this->ability(), $employee) ?? false)) {
                abort(403);
            }
        }

        return $employees;
    }
}

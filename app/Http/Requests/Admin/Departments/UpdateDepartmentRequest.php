<?php

namespace App\Http\Requests\Admin\Departments;

use App\Concerns\DepartmentRules;
use App\Enums\DepartmentStatus;
use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Edit a department's typed fields with the same rules as create. The scope
 * is not editable (DepartmentService::update says why), so the slug rule
 * checks uniqueness inside the catalogue the row already lives in.
 */
class UpdateDepartmentRequest extends FormRequest
{
    use DepartmentRules;

    public function authorize(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && ($this->user()?->can('update', $department) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) $this->input('name', '')),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $department = $this->department();

        return [
            ...$this->departmentFieldRules(),
            'slug' => ['nullable', 'string', 'max:120', $this->departmentSlugRule($department->hotel_id, $department->id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->departmentMessages();
    }

    /**
     * @return array{name: string, slug: string, focus: ?string, status: DepartmentStatus}
     */
    public function departmentData(): array
    {
        /** @var array{name: string, slug: ?string, focus?: ?string, status: string} $data */
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'slug' => $data['slug'] ?? '',
            'focus' => $data['focus'] ?? null,
            'status' => DepartmentStatus::from($data['status']),
        ];
    }

    private function department(): Department
    {
        $department = $this->route('department');

        if (! $department instanceof Department) {
            abort(404);
        }

        return $department;
    }
}

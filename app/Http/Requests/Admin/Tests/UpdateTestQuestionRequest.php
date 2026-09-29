<?php

namespace App\Http\Requests\Admin\Tests;

use App\Enums\Permission;
use App\Models\ActivityPlacement;
use App\Models\Test;

/**
 * Update a question through a new Activity version (DATA-11, TEST-09). An
 * activity from the shared editor keeps its stored type.
 */
class UpdateTestQuestionRequest extends StoreTestQuestionRequest
{
    public function authorize(): bool
    {
        $test = $this->route('test');
        $placement = $this->route('placement');

        return $test instanceof Test
            && $placement instanceof ActivityPlacement
            && $placement->placeable_type === $test->getMorphClass()
            && (int) $placement->placeable_id === (int) $test->id
            && ($this->user()?->can(Permission::TestsManage->value) ?? false);
    }

    protected function typeIsFixed(): bool
    {
        return true;
    }
}

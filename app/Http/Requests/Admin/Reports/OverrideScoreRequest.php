<?php

namespace App\Http\Requests\Admin\Reports;

use App\Models\Attempt;
use App\Models\RoleplayAttempt;
use App\Services\Reports\ScoreOverrides;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * An admin's replacement score and the reason for it (AIE-05; spec 0005
 * §2.5). Works for a test/practice answer (`{attempt}`) and for a role-play
 * conversation (`{roleplayAttempt}`); the policy decides who may, and the
 * score is bounded by what the item can score.
 */
class OverrideScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('overrideScore', $this->target());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $target = $this->target();
        $max = $target instanceof Attempt ? ScoreOverrides::maxFor($target) : ScoreOverrides::DEFAULT_MAX;

        return [
            'score' => [
                'required',
                'numeric',
                'min:0',
                'max:'.$max,
                ...($target instanceof RoleplayAttempt ? ['integer'] : []),
            ],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => __('Say why the score changes; it is kept with the answer.'),
        ];
    }

    public function score(): float
    {
        return (float) $this->validated('score');
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }

    public function target(): Attempt|RoleplayAttempt
    {
        $target = $this->route('attempt') ?? $this->route('roleplayAttempt');

        abort_unless($target instanceof Attempt || $target instanceof RoleplayAttempt, 404);

        return $target;
    }
}

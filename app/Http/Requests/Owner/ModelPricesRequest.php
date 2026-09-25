<?php

namespace App\Http\Requests\Owner;

use App\Models\AiModelPrice;
use App\Models\Owner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The whole price table (spec 0007, D8/D9): per million units, in the
 * currency the owner is billed in. A model id may end in `*` to price
 * every model with that prefix.
 */
class ModelPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('owner') instanceof Owner;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prices' => ['present', 'array', 'max:200'],
            'prices.*.model' => ['required', 'string', 'max:150', 'distinct', 'regex:/^[A-Za-z0-9_.:\/\-]+\*?$/'],
            'prices.*.unit' => ['required', Rule::in(AiModelPrice::UNITS)],
            'prices.*.input' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'prices.*.output' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prices.*.model.regex' => __('Use the model id as the usage table shows it; end it with * to cover every model that starts the same way.'),
        ];
    }

    /**
     * @return list<array{model: string, unit: string, input: float, output: float}>
     */
    public function rows(): array
    {
        $rows = [];

        foreach ((array) $this->input('prices', []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[] = [
                'model' => trim((string) ($row['model'] ?? '')),
                'unit' => (string) ($row['unit'] ?? 'tokens'),
                'input' => round((float) ($row['input'] ?? 0), 4),
                'output' => round((float) ($row['output'] ?? 0), 4),
            ];
        }

        return $rows;
    }
}

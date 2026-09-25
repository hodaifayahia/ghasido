<?php

namespace App\Http\Requests\Owner;

use App\Models\Owner;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A new API key for a paid account (spec 0007, D2). One token with no
 * spaces; never echoed back, not even in a validation error.
 */
class ApiKeyRequest extends FormRequest
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
            'key' => ['required', 'string', 'min:8', 'max:500', 'regex:/^\S+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.regex' => __('Paste the key only, with no spaces.'),
        ];
    }

    public function key(): string
    {
        return trim($this->string('key')->value());
    }

    /**
     * Never flash the key back into the session with the old input.
     *
     * @var list<string>
     */
    protected $dontFlash = ['key'];
}

<?php

namespace App\Http\Requests\Owner;

use App\Enums\ApiAccount;
use App\Models\Owner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * One recharge (spec 0007, D4): dollars, and tokens for an account that
 * counts them. A negative amount corrects an earlier mistake; a row must
 * move at least one of the two.
 */
class CreditTopupRequest extends FormRequest
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
        $account = $this->route('account');
        $tokens = $account instanceof ApiAccount && $account->tracksTokens();

        return [
            'usd' => ['nullable', 'numeric', 'between:-1000000,1000000', 'decimal:0,4'],
            'tokens' => $tokens
                ? ['nullable', 'integer', 'between:-1000000000000,1000000000000']
                : ['prohibited'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && $this->usd() == 0 && $this->tokens() === 0) {
                    $validator->errors()->add('usd', __('Enter an amount to add.'));
                }
            },
        ];
    }

    public function usd(): float
    {
        return round((float) $this->input('usd', 0), 4);
    }

    public function tokens(): int
    {
        return (int) $this->input('tokens', 0);
    }

    public function note(): ?string
    {
        $note = trim($this->string('note')->value());

        return $note === '' ? null : $note;
    }
}

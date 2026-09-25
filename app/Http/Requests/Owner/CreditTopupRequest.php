<?php

namespace App\Http\Requests\Owner;

use App\Enums\ApiAccount;
use App\Enums\CreditMeter;
use App\Models\Owner;
use App\Services\Owner\ApiCredit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * One recharge (spec 0007, D4, D10): the dollars the Super Admin is
 * credited, and the provider units they buy (Qwen tokens; Deepgram audio
 * minutes and speech characters). A negative amount corrects an earlier
 * mistake; a row must move at least one amount.
 */
class CreditTopupRequest extends FormRequest
{
    private const int MAX_UNITS = 1_000_000_000_000;

    public function authorize(): bool
    {
        return $this->user('owner') instanceof Owner;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $account = $this->account();
        $meters = $account?->meters() ?? [];
        $rules = [
            'usd' => ['nullable', 'numeric', 'between:-1000000,1000000', 'decimal:0,4'],
            'note' => ['nullable', 'string', 'max:255'],
        ];

        foreach (CreditMeter::cases() as $meter) {
            $rules[$meter->inputField()] = in_array($meter, $meters, true)
                ? ['nullable', 'integer', 'between:-'.self::MAX_UNITS.','.self::MAX_UNITS]
                : ['prohibited'];
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $account = $this->account();

                if ($account === null || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $units = array_filter($this->meterAmounts($account));

                if ($this->usd() == 0 && $units === []) {
                    $validator->errors()->add('usd', __('Enter an amount to add.'));

                    return;
                }

                // Once an account is counted in units, dollars alone would
                // raise the balance she sees without buying anything (D10).
                $first = $account->meters()[0];

                if ($this->usd() > 0 && $units === [] && app(ApiCredit::class)->balance($account)->mode() === 'units') {
                    $validator->errors()->add($first->inputField(), __('This account is counted in :units: add how many this recharge buys.', [
                        'units' => mb_strtolower(implode(' / ', array_map(fn (CreditMeter $meter): string => $meter->label(), $account->meters()))),
                    ]));
                }
            },
        ];
    }

    public function usd(): float
    {
        return round((float) $this->input('usd', 0), 4);
    }

    /**
     * The units this recharge buys, keyed by ledger column, in stored units
     * (minutes become seconds).
     *
     * @return array<string, int>
     */
    public function meterAmounts(ApiAccount $account): array
    {
        $amounts = [];

        foreach ($account->meters() as $meter) {
            $amounts[$meter->column()] = (int) $this->input($meter->inputField(), 0) * $meter->inputScale();
        }

        return $amounts;
    }

    public function note(): ?string
    {
        $note = trim($this->string('note')->value());

        return $note === '' ? null : $note;
    }

    private function account(): ?ApiAccount
    {
        $account = $this->route('account');

        return $account instanceof ApiAccount ? $account : null;
    }
}

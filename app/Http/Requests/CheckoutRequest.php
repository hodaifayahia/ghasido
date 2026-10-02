<?php

namespace App\Http\Requests;

use App\Concerns\HelperLanguageRequestRules;
use App\Models\Department;
use App\Models\SubscriptionPaymentMethod;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Password;

/**
 * Buying a plan online with a manual payment (client request 2026-09-27).
 *
 * A hotel plan asks for the hotel and its first manager; an individual plan
 * for the learner and the department they study. Both then name the payment
 * method used and prove the payment with a receipt (image or PDF, 5 MB at
 * most), a typed transaction reference, or both. The receipt is checked by
 * type and size on the server (SEC-04).
 */
class CheckoutRequest extends FormRequest
{
    use HelperLanguageRequestRules;

    /** The receipt formats accepted, and their size cap in kilobytes. */
    public const array PROOF_TYPES = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    public const int PROOF_MAX_KB = 5 * 1024;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // A plan that is not on sale is not found, before any field is checked.
        abort_unless($this->plan()->is_active, 404);

        $lower = fn (mixed $value): mixed => is_string($value) ? mb_strtolower(trim($value)) : $value;
        $trim = fn (mixed $value): mixed => is_string($value) ? trim($value) : $value;

        $this->merge([
            'manager_username' => $lower($this->input('manager_username')),
            'manager_email' => $lower($this->input('manager_email')),
            'username' => $lower($this->input('username')),
            'email' => $lower($this->input('email')),
            'phone' => $trim($this->input('phone')),
            'reference' => $trim($this->input('reference')),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'region' => ['nullable', Rule::in(['dz', 'intl'])],
            'phone' => ['required', 'string', 'max:40', 'regex:/^\+?[0-9().\s-]{7,40}$/'],
            'password' => ['required', 'confirmed', 'string', Password::min(8), 'max:72'],
        ];

        $rules += $this->plan()->isIndividual()
            ? [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
                'username' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9._-]+$/', Rule::unique(User::class, 'username')],
                'department_id' => ['required', 'integer', Rule::exists(Department::class, 'id')->whereNull('hotel_id')->where('is_active', true)],
            ]
            : [
                'name' => ['required', 'string', 'max:120'],
                'city' => ['required', 'string', 'max:120'],
                'manager_name' => ['required', 'string', 'max:120'],
                'manager_email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
                'manager_username' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9._-]+$/', Rule::unique(User::class, 'username')],
            ];

        // The payment is always proved, by a receipt, a reference or both.
        // The method is asked for only once one is set up for customers.
        $rules += [
            'proof' => ['nullable', 'required_without:reference', File::types(self::PROOF_TYPES)->max(self::proofMaxKb())],
            'reference' => ['nullable', 'required_without:proof', 'string', 'max:120'],
        ];

        $rules += $this->helperLanguageRules();

        if (self::acceptsPayments()) {
            $rules['payment_method_id'] = ['required', 'integer', Rule::exists(SubscriptionPaymentMethod::class, 'id')->where('is_active', true)];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'manager_username.regex' => __('Usernames use lowercase letters, digits, dots, dashes and underscores only.'),
            'username.regex' => __('Usernames use lowercase letters, digits, dots, dashes and underscores only.'),
            'manager_username.unique' => __('That username is already taken.'),
            'username.unique' => __('That username is already taken.'),
            'manager_email.unique' => __('That email address is already in use.'),
            'email.unique' => __('That email address is already in use.'),
            'phone.regex' => __('Enter a phone number with 7 digits or more.'),
            'proof.required_without' => __('Upload the payment receipt or type the transaction reference.'),
            'reference.required_without' => __('Upload the payment receipt or type the transaction reference.'),
            'payment_method_id.required' => __('Choose how you paid.'),
            'proof.uploaded' => __('The receipt could not be uploaded. Try a smaller file (under :size MB) or type the transaction reference.', ['size' => self::proofMaxMb()]),
        ];
    }

    /**
     * The receipt size cap in kilobytes: 5 MB, or less when the server's PHP
     * accepts less (upload_max_filesize / post_max_size), so the customer
     * is told the real limit instead of the file vanishing on the way.
     */
    public static function proofMaxKb(): int
    {
        $limits = [self::PROOF_MAX_KB];

        foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
            $bytes = self::iniBytes((string) ini_get($setting));

            if ($bytes > 0) {
                // Leave room for the form's other fields in the POST body.
                $limits[] = intdiv($bytes, 1024) - ($setting === 'post_max_size' ? 64 : 0);
            }
        }

        return max(256, min($limits));
    }

    public static function proofMaxMb(): string
    {
        return rtrim(rtrim(number_format(self::proofMaxKb() / 1024, 1, '.', ''), '0'), '.');
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1' || $value === '0') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /** Is at least one payment method ready to show customers? */
    public static function acceptsPayments(): bool
    {
        return SubscriptionPaymentMethod::query()
            ->where('is_active', true)
            ->whereNotNull('account_reference')
            ->whereRaw("trim(account_reference) <> ''")
            ->exists();
    }

    public function plan(): SubscriptionPlan
    {
        $plan = $this->route('plan');
        abort_unless($plan instanceof SubscriptionPlan, 404);

        return $plan;
    }

    /** `USD` for the international price, `DZD` otherwise. */
    public function currency(): string
    {
        return $this->input('region') === 'intl' ? 'USD' : 'DZD';
    }

    /** @return array{name: string, city: string, manager_name: string, manager_email: string} */
    public function hotelData(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'city' => $this->string('city')->toString(),
            'manager_name' => $this->string('manager_name')->toString(),
            'manager_email' => $this->string('manager_email')->toString(),
        ];
    }

    /** @return array{name: string, username: string, email: string, password: string, phone: string, locale: string} */
    public function managerData(): array
    {
        return [
            'name' => $this->string('manager_name')->toString(),
            'username' => $this->string('manager_username')->toString(),
            'email' => $this->string('manager_email')->toString(),
            'password' => $this->string('password')->toString(),
            'phone' => $this->string('phone')->toString(),
            'locale' => app()->getLocale(),
        ];
    }

    /** @return array{name: string, username: string, email: string, phone: string|null, password: string, locale: string} */
    public function individualData(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'username' => $this->string('username')->toString(),
            'email' => $this->string('email')->toString(),
            'phone' => $this->string('phone')->toString(),
            'password' => $this->string('password')->toString(),
            'locale' => app()->getLocale(),
        ];
    }

    public function departmentId(): int
    {
        return $this->integer('department_id');
    }

    public function paymentMethod(): ?SubscriptionPaymentMethod
    {
        return self::acceptsPayments()
            ? SubscriptionPaymentMethod::query()->find($this->integer('payment_method_id'))
            : null;
    }

    public function proof(): ?UploadedFile
    {
        $file = $this->file('proof');

        return $file instanceof UploadedFile ? $file : null;
    }

    /** @return array{currency: 'DZD'|'USD', reference: string|null, payer_name: string, payer_email: string, payer_phone: string|null} */
    public function paymentData(): array
    {
        $individual = $this->plan()->isIndividual();
        $reference = $this->string('reference')->toString();

        return [
            'currency' => $this->currency() === 'USD' ? 'USD' : 'DZD',
            'reference' => $reference === '' ? null : $reference,
            'payer_name' => $this->string($individual ? 'name' : 'manager_name')->toString(),
            'payer_email' => $this->string($individual ? 'email' : 'manager_email')->toString(),
            'payer_phone' => $this->string('phone')->toString(),
        ];
    }
}

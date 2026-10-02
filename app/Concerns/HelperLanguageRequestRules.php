<?php

namespace App\Concerns;

use App\Services\Meaning\HelperLanguages;
use Illuminate\Validation\Rule;

/**
 * "Which languages would help you understand English?" on the sign-up and
 * checkout forms (client request 2026-10-01): any of the offered helper
 * languages, plus one more the customer types when theirs is not offered.
 * Optional, so a customer who does not care is never stopped.
 */
trait HelperLanguageRequestRules
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function helperLanguageRules(): array
    {
        return [
            'helper_languages' => ['nullable', 'array', 'max:6'],
            'helper_languages.*' => ['string', Rule::in(app(HelperLanguages::class)->activeCodes())],
            'helper_language_other' => ['nullable', 'string', 'max:60', 'regex:/^[\pL\pM\s\'().-]+$/u'],
        ];
    }

    /**
     * The languages asked for: offered ones by code, the typed one by name.
     *
     * @return list<array{code: string|null, name: string}>
     */
    public function requestedHelperLanguages(): array
    {
        $languages = app(HelperLanguages::class);
        $requested = [];

        foreach ((array) $this->input('helper_languages', []) as $code) {
            if (is_string($code) && $languages->isActive($code) && ! in_array($code, array_column($requested, 'code'), true)) {
                $requested[] = ['code' => $code, 'name' => $languages->name($code)];
            }
        }

        $other = trim((string) $this->input('helper_language_other', ''));

        if ($other !== '') {
            $requested[] = ['code' => null, 'name' => mb_substr($other, 0, 60)];
        }

        return $requested;
    }
}

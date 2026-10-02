<?php

namespace App\Services\Meaning;

use App\Models\User;

/**
 * The helper languages a customer asked for at sign-up (client request
 * 2026-10-01). Saved on the account that sent the request; the first
 * offered one becomes that account's Show Meaning language. The Super
 * Admin sees them on the request with a shortcut to translate the lessons
 * into each one, or to add a language that is not offered yet.
 */
final class RequestedHelperLanguages
{
    public function __construct(private readonly HelperLanguages $languages) {}

    /**
     * @param  list<array{code: string|null, name: string}>  $requested
     */
    public function apply(User $user, array $requested): void
    {
        if ($requested === []) {
            return;
        }

        $user->requested_helper_languages = $requested;

        foreach ($requested as $language) {
            if ($language['code'] !== null && $this->languages->isActive($language['code'])) {
                $user->meaning_locale ??= $language['code'];

                break;
            }
        }

        $user->save();
    }

    /**
     * What a request shows the Super Admin: each language with its flag,
     * whether it is offered (on the list), and where to act on it.
     *
     * @return list<array{code: string|null, name: string, native: string|null, flag: string|null, offered: bool, translationsUrl: string|null}>
     */
    public function describe(?User $user): array
    {
        $out = [];

        foreach ($user->requested_helper_languages ?? [] as $item) {
            $code = $item['code'] ?? $this->codeFor($item['name']);
            $known = $code !== null ? $this->languages->find($code) : null;

            $out[] = [
                'code' => $known['code'] ?? null,
                'name' => $known['name'] ?? $item['name'],
                'native' => $known['native'] ?? null,
                'flag' => $known !== null ? HelperLanguages::flag($known['code']) : null,
                // Switched off counts as not offered: learners cannot pick it.
                'offered' => $known !== null && $known['active'],
                'translationsUrl' => $known !== null ? route('translations', ['language' => $known['code']]) : null,
            ];
        }

        return $out;
    }

    /** A typed name the Super Admin has since added ("Turkish" → tr). */
    private function codeFor(string $name): ?string
    {
        foreach ($this->languages->all() as $language) {
            if (strcasecmp($language['name'], trim($name)) === 0 || strcasecmp($language['native'], trim($name)) === 0) {
                return $language['code'];
            }
        }

        return null;
    }
}

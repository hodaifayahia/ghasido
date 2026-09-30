<?php

namespace App\Services\Meaning;

use App\Models\User;
use App\Services\Platform\PlatformSettings;

/**
 * The helper languages of Show Meaning (client request 2026-09-30): Arabic
 * and French to start. They are data, not code: the Super Admin adds a
 * language (Malay, Indonesian, Turkish…), switches one off or picks the
 * default in Settings → Learning, and every translation, AI draft and
 * learner choice follows without a developer.
 *
 * A language switched off is kept, with its translations, so switching it
 * back on loses nothing (DATA-10).
 */
final class HelperLanguages
{
    public const LANGUAGES = 'meaning.languages';

    public const DEFAULT = 'meaning.default';

    /** Arabic and French, the first two (client request 2026-09-30). */
    public const array SEED = [
        ['code' => 'ar', 'name' => 'Arabic', 'native' => 'العربية', 'dir' => 'rtl', 'active' => true],
        ['code' => 'fr', 'name' => 'French', 'native' => 'Français', 'dir' => 'ltr', 'active' => true],
    ];

    public function __construct(private readonly PlatformSettings $settings) {}

    /**
     * Every language, active or not.
     *
     * @return list<array{code: string, name: string, native: string, dir: string, active: bool}>
     */
    public function all(): array
    {
        $stored = $this->settings->get(self::LANGUAGES)['items'] ?? null;

        if (! is_array($stored) || $stored === []) {
            return self::SEED;
        }

        $languages = [];

        foreach ($stored as $item) {
            if (! is_array($item) || ! is_string($item['code'] ?? null) || $item['code'] === '') {
                continue;
            }

            $languages[] = [
                'code' => $item['code'],
                'name' => (string) ($item['name'] ?? $item['code']),
                'native' => (string) ($item['native'] ?? $item['name'] ?? $item['code']),
                'dir' => ($item['dir'] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr',
                'active' => (bool) ($item['active'] ?? true),
            ];
        }

        return $languages === [] ? self::SEED : $languages;
    }

    /**
     * The languages learners may choose and the AI drafts.
     *
     * @return list<array{code: string, name: string, native: string, dir: string, active: bool}>
     */
    public function active(): array
    {
        return array_values(array_filter($this->all(), fn (array $language): bool => $language['active']));
    }

    /** @return list<string> */
    public function activeCodes(): array
    {
        return array_column($this->active(), 'code');
    }

    /** @return array{code: string, name: string, native: string, dir: string, active: bool}|null */
    public function find(string $code): ?array
    {
        foreach ($this->all() as $language) {
            if ($language['code'] === $code) {
                return $language;
            }
        }

        return null;
    }

    public function isActive(?string $code): bool
    {
        return $code !== null && in_array($code, $this->activeCodes(), true);
    }

    /** The language learners start with until they choose one. */
    public function defaultCode(): string
    {
        $stored = $this->settings->get(self::DEFAULT)['code'] ?? null;

        if (is_string($stored) && $this->isActive($stored)) {
            return $stored;
        }

        return $this->activeCodes()[0] ?? 'ar';
    }

    /** The helper language this learner reads meanings in. */
    public function forUser(?User $user): string
    {
        $chosen = $user?->meaning_locale;

        return $this->isActive($chosen) ? (string) $chosen : $this->defaultCode();
    }

    public function name(string $code): string
    {
        return $this->find($code)['name'] ?? $code;
    }

    public function direction(string $code): string
    {
        return $this->find($code)['dir'] ?? 'ltr';
    }

    /**
     * @param  list<array{code: string, name: string, native: string, dir: string, active: bool}>  $languages
     */
    public function save(array $languages, string $default, User $actor): void
    {
        $this->settings->put(self::LANGUAGES, ['items' => $languages], $actor);
        $this->settings->put(self::DEFAULT, ['code' => $default], $actor);
    }

    /**
     * For the learner's own chooser (shared prop).
     *
     * @return list<array{value: string, label: string, dir: string}>
     */
    public function options(): array
    {
        return array_map(
            fn (array $language): array => [
                'value' => $language['code'],
                'label' => $language['native'] !== $language['name']
                    ? $language['native'].' ('.$language['name'].')'
                    : $language['name'],
                'dir' => $language['dir'],
            ],
            $this->active(),
        );
    }
}

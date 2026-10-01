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

    /**
     * The flag shown next to each language (client request 2026-10-01), by
     * language code: Arabic carries Algeria's, the client's market.
     */
    public const array FLAGS = [
        'ar' => 'dz', 'fr' => 'fr', 'en' => 'gb', 'es' => 'es', 'de' => 'de',
        'it' => 'it', 'tr' => 'tr', 'id' => 'id', 'pt' => 'pt', 'ru' => 'ru',
        'nl' => 'nl', 'zh' => 'cn', 'ja' => 'jp',
    ];

    public function __construct(private readonly PlatformSettings $settings) {}

    /** The country flag of a language, or null when there is none. */
    public static function flag(string $code): ?string
    {
        return self::FLAGS[strtolower($code)] ?? null;
    }

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
     * For the language chooser (shared prop): flag, own name and English
     * name of each active language.
     *
     * @return list<array{value: string, label: string, dir: string, name: string, native: string, flag: string|null}>
     */
    public function options(): array
    {
        return array_map(fn (array $language): array => $this->option($language), $this->active());
    }

    /**
     * Every language, switched off or not, for the builders' translation
     * field: a translation can be written before a language goes live.
     *
     * @return list<array{value: string, label: string, dir: string, name: string, native: string, flag: string|null, active: bool}>
     */
    public function editorOptions(): array
    {
        return array_map(
            fn (array $language): array => [...$this->option($language), 'active' => $language['active']],
            $this->all(),
        );
    }

    /**
     * @param  array{code: string, name: string, native: string, dir: string, active: bool}  $language
     * @return array{value: string, label: string, dir: string, name: string, native: string, flag: string|null}
     */
    private function option(array $language): array
    {
        return [
            'value' => $language['code'],
            'label' => $language['native'] !== $language['name']
                ? $language['native'].' ('.$language['name'].')'
                : $language['name'],
            'dir' => $language['dir'],
            'name' => $language['name'],
            'native' => $language['native'],
            'flag' => self::flag($language['code']),
        ];
    }
}

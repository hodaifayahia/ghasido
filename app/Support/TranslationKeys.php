<?php

namespace App\Support;

use Symfony\Component\Finder\Finder;

/**
 * Every interface string the code asks to translate (I18N-02): `$t()`,
 * `t()`, `$tc()`, `tc()`, `tk()` and `<TransText text>` in the Vue and
 * TypeScript sources, and `__()` / `trans_choice()` in the PHP. The keys are
 * the English strings themselves, looked up in `lang/ar.json`.
 */
final class TranslationKeys
{
    private const string JS_CALL = '/(?<![\w$.])(?:\$t|t|\$tc|tc|tk)\(\s*([\'"`])((?:\\\\.|(?!\1).)*?)\1\s*[,)]/s';

    private const string TRANS_TEXT = '/<TransText\b[^>]*?\stext="([^"]*)"/s';

    private const string PHP_CALL = '/(?<![\w>$:])(?:__|trans_choice)\(\s*([\'"])((?:\\\\.|(?!\1).)*?)\1\s*[,)]/s';

    /** @return list<string> sorted, distinct keys */
    public static function all(): array
    {
        $keys = [];

        foreach (self::files(resource_path('js'), ['*.vue', '*.ts'], ['components/ui', 'routes', 'actions', 'wayfinder']) as $path => $source) {
            foreach (self::matches(self::JS_CALL, $source) as [$quote, $text]) {
                // A template literal with ${…} is not a fixed key.
                if ($quote === '`' && str_contains($text, '${')) {
                    continue;
                }

                $keys[] = self::unescape($text);
            }

            foreach (self::matches(self::TRANS_TEXT, $source, 1) as [$text]) {
                $keys[] = html_entity_decode($text, ENT_QUOTES);
            }
        }

        foreach ([app_path(), base_path('routes')] as $directory) {
            foreach (self::files($directory, ['*.php'], []) as $path => $source) {
                foreach (self::matches(self::PHP_CALL, $source) as [, $text]) {
                    $keys[] = self::unescape($text);
                }
            }
        }

        $keys = array_values(array_unique(array_filter(
            $keys,
            // Laravel's own grouped keys (validation.required, auth.failed)
            // live in lang/ar/*.php, not the JSON dictionary.
            fn (string $key): bool => trim($key) !== '' && preg_match('/^[a-z_]+\.[a-z_.]+$/', $key) !== 1,
        )));
        sort($keys);

        return $keys;
    }

    /** @return array<string, string> */
    public static function dictionary(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        if (! is_file($path)) {
            return [];
        }

        /** @var array<string, string> $entries */
        $entries = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $entries;
    }

    /** @return list<string> the keys with no entry in the locale's dictionary */
    public static function missing(string $locale): array
    {
        $dictionary = self::dictionary($locale);

        return array_values(array_filter(
            self::all(),
            fn (string $key): bool => ! isset($dictionary[$key]) || trim($dictionary[$key]) === '',
        ));
    }

    /**
     * @param  list<string>  $patterns
     * @param  list<string>  $exclude
     * @return iterable<string, string>
     */
    private static function files(string $directory, array $patterns, array $exclude): iterable
    {
        $finder = Finder::create()->files()->in($directory)->name($patterns)->exclude($exclude);

        foreach ($finder as $file) {
            yield $file->getRealPath() => $file->getContents();
        }
    }

    /** @return list<list<string>> */
    private static function matches(string $pattern, string $source, int $groups = 2): array
    {
        preg_match_all($pattern, $source, $found, PREG_SET_ORDER);

        return array_map(
            fn (array $match): array => array_slice(array_values($match), 1, $groups),
            $found,
        );
    }

    private static function unescape(string $text): string
    {
        return strtr($text, ['\\\\' => '\\', "\\'" => "'", '\\"' => '"', '\\`' => '`', '\\n' => "\n"]);
    }
}

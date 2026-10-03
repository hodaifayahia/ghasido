<?php

namespace App\Services\I18n;

use App\Models\InterfaceLanguage;
use App\Services\Meaning\HelperLanguages;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The interface languages added from Settings (client request 2026-10-03),
 * beside the built-in English and Arabic. Read on every request, so the
 * list and each language's strings are cached until an edit.
 */
final class InterfaceLanguages
{
    private const string LIST_KEY = 'interface-languages:list';

    /** @var list<array{code: string, name: string, native: string, dir: string}> */
    public const array BUILT_IN = [
        ['code' => 'en', 'name' => 'English', 'native' => 'English', 'dir' => 'ltr'],
        ['code' => 'ar', 'name' => 'Arabic', 'native' => 'العربية', 'dir' => 'rtl'],
    ];

    /**
     * The enabled added languages.
     *
     * @return list<array{code: string, name: string, native: string, dir: string}>
     */
    public static function enabled(): array
    {
        try {
            /** @var list<array{code: string, name: string, native: string, dir: string}> */
            return Cache::rememberForever(self::LIST_KEY, function (): array {
                if (! Schema::hasTable('interface_languages')) {
                    return [];
                }

                return InterfaceLanguage::query()
                    ->where('enabled', true)
                    ->orderBy('name')
                    ->get()
                    ->map(fn (InterfaceLanguage $language): array => [
                        'code' => $language->code,
                        'name' => $language->name,
                        'native' => $language->native_name,
                        'dir' => $language->direction === 'rtl' ? 'rtl' : 'ltr',
                    ])
                    ->values()
                    ->all();
            });
        } catch (Throwable) {
            // No database yet (a fresh install, a build step): built-ins only.
            return [];
        }
    }

    /**
     * Every language the menu offers, for the shared `locale` prop.
     *
     * @return list<array{code: string, name: string, native: string, dir: string, flag: string|null}>
     */
    public static function options(): array
    {
        return array_map(
            fn (array $language): array => [...$language, 'flag' => HelperLanguages::flag($language['code'])],
            [...self::BUILT_IN, ...self::enabled()],
        );
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_column([...self::BUILT_IN, ...self::enabled()], 'code');
    }

    public static function direction(string $code): string
    {
        foreach ([...self::BUILT_IN, ...self::enabled()] as $language) {
            if ($language['code'] === $code) {
                return $language['dir'];
            }
        }

        return 'ltr';
    }

    /**
     * An added language's strings, English key → translation. Empty for a
     * built-in language, whose strings are lang/*.json.
     *
     * @return array<string, string>
     */
    public static function messages(string $code): array
    {
        if (in_array($code, array_column(self::BUILT_IN, 'code'), true)) {
            return [];
        }

        try {
            /** @var array<string, string> */
            return Cache::rememberForever(self::messagesKey($code), function () use ($code): array {
                if (! Schema::hasTable('interface_languages')) {
                    return [];
                }

                return InterfaceLanguage::query()->where('code', $code)->first()?->translations() ?? [];
            });
        } catch (Throwable) {
            return [];
        }
    }

    public static function forget(string $code): void
    {
        Cache::forget(self::LIST_KEY);
        Cache::forget(self::messagesKey($code));
    }

    /**
     * The strings to translate: every key of the Arabic dictionary (the
     * coverage test keeps it complete), with its English text — the key
     * itself, or lang/en.json's text for a context key.
     *
     * @return array<string, string> key → English text
     */
    public static function sourceStrings(): array
    {
        $arabic = self::readJson(lang_path('ar.json'));
        $english = self::readJson(lang_path('en.json'));
        $strings = [];

        foreach (array_keys($arabic) as $key) {
            $strings[$key] = $english[$key] ?? $key;
        }

        return $strings;
    }

    /** @return array<string, string> */
    private static function readJson(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $data = json_decode((string) File::get($path), true);

        return is_array($data) ? array_filter($data, 'is_string') : [];
    }

    private static function messagesKey(string $code): string
    {
        return 'interface-languages:messages:'.$code;
    }
}

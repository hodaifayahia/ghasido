<?php

namespace App\Support;

use App\Services\I18n\InterfaceLanguages;

/**
 * The interface languages (I18N-02, user request 2026-09-26): English, the
 * default, and Arabic, laid out right to left. Learning content itself stays
 * English; its Arabic lives behind Show Meaning (I18N-01, CTRL-01).
 */
final class Locales
{
    public const string COOKIE = 'locale';

    /**
     * The language for anyone who has not chosen one. A constant rather than
     * `config('app.locale')`, which App::setLocale() overwrites for the rest
     * of a long-lived process.
     */
    public const string DEFAULT = 'en';

    /** @var list<string> */
    public const array SUPPORTED = ['en', 'ar'];

    /** @var list<string> */
    public const array RTL = ['ar'];

    /**
     * English, Arabic, and any language added and enabled in Settings
     * (client request 2026-10-03).
     */
    public static function isSupported(mixed $locale): bool
    {
        return is_string($locale) && in_array($locale, InterfaceLanguages::codes(), true);
    }

    public static function direction(string $locale): string
    {
        return in_array($locale, self::RTL, true) ? 'rtl' : InterfaceLanguages::direction($locale);
    }
}

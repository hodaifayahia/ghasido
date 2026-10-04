<?php

namespace App\Support;

/**
 * What search engines read before any JavaScript runs (client report
 * 2026-10-04: "GHASIDO does not come first when I search for it"). The app
 * renders in the browser, so the server writes the title, description,
 * social tags and a readable copy of the landing page itself; every private
 * screen is marked noindex so the public pages carry the site.
 */
final class Seo
{
    /** Public pages search engines should list. */
    public const array PUBLIC_PAGES = ['Welcome', 'Contact', 'auth/HotelSignup'];

    public const string SITE_NAME = 'GHASIDO';

    public const string KEYWORDS = 'GHASIDO, hotel English, English for hotel staff, hospitality English training, hotel staff training Algeria, AI role-play English, تعليم الإنجليزية لموظفي الفنادق';

    /**
     * @param  array<string, mixed>  $page  the Inertia page
     * @return array{indexable: bool, title: string, description: string, canonical: string|null}
     */
    public static function for(array $page): array
    {
        $component = (string) ($page['component'] ?? '');
        $content = (array) data_get($page, 'props.content', []);
        $tagline = 'English training for hotel staff';

        return match ($component) {
            'Welcome' => [
                'indexable' => true,
                'title' => self::SITE_NAME.' – '.self::text(data_get($content, 'hero.title'), 'Hotel English your team can use on their next shift.'),
                'description' => self::text(data_get($content, 'hero.description'), 'GHASIDO brings lessons, assessments, AI guest role-play and measurable progress into one training path for every hotel department.'),
                'canonical' => url('/'),
            ],
            'Contact' => [
                'indexable' => true,
                'title' => self::text(data_get($content, 'contact.title'), 'Contact us').' – '.self::SITE_NAME,
                'description' => self::text(data_get($content, 'contact.description'), 'Contact the GHASIDO team about English training for your hotel staff.'),
                'canonical' => url('/contact'),
            ],
            'auth/HotelSignup' => [
                'indexable' => true,
                'title' => 'Register your hotel – '.self::SITE_NAME,
                'description' => 'Request GHASIDO for your hotel: English training for every department, with AI role-play and progress reports.',
                'canonical' => url('/hotel-signup'),
            ],
            default => [
                'indexable' => false,
                'title' => self::SITE_NAME.' – '.$tagline,
                'description' => 'GHASIDO: '.$tagline.'.',
                'canonical' => null,
            ],
        };
    }

    private static function text(mixed $value, string $fallback): string
    {
        $text = is_string($value) ? trim(strip_tags($value)) : '';

        return $text !== '' ? $text : $fallback;
    }

    /**
     * The site name, logo and description as schema.org data, for the
     * search result card.
     *
     * @param  array{indexable: bool, title: string, description: string, canonical: string|null}  $seo
     */
    public static function jsonLd(array $seo): string
    {
        $logo = url('/brand/ghasido-app-512.png');

        return (string) json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebSite',
                    'name' => self::SITE_NAME,
                    'alternateName' => ['Ghasido', 'GHASIDO English for Hotel Staff'],
                    'url' => url('/'),
                    'description' => $seo['description'],
                    'inLanguage' => ['en', 'ar'],
                ],
                [
                    '@type' => 'Organization',
                    'name' => self::SITE_NAME,
                    'url' => url('/'),
                    'logo' => $logo,
                    'description' => $seo['description'],
                ],
                [
                    '@type' => 'SoftwareApplication',
                    'name' => self::SITE_NAME,
                    'applicationCategory' => 'EducationalApplication',
                    'operatingSystem' => 'Web, Android, iOS',
                    'url' => url('/'),
                    'image' => $logo,
                    'description' => $seo['description'],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }
}

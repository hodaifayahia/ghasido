<?php

namespace Tests\Feature\I18n;

use App\Support\TranslationKeys;
use Tests\TestCase;

/**
 * The Arabic interface is complete (I18N-02, user request 2026-09-26): every
 * string the code translates has an Arabic entry, and every entry keeps the
 * `:placeholders` its English key has, so no value goes missing.
 */
class TranslationsCoverageTest extends TestCase
{
    public function test_every_interface_string_has_an_arabic_translation(): void
    {
        $missing = TranslationKeys::missing('ar');

        $this->assertSame([], $missing, 'Run `php artisan i18n:missing` and add these to lang/ar.json.');
    }

    public function test_arabic_translations_keep_every_placeholder(): void
    {
        $broken = [];

        foreach (TranslationKeys::dictionary('ar') as $english => $arabic) {
            preg_match_all('/:([A-Za-z_]+)/', $english, $expected);
            preg_match_all('/:([A-Za-z_]+)/', $arabic, $actual);

            $lost = array_diff(array_unique($expected[1]), $actual[1]);

            if ($lost !== []) {
                $broken[$english] = 'missing :'.implode(', :', $lost);
            }
        }

        $this->assertSame([], $broken);
    }

    public function test_the_arabic_dictionary_is_valid_json_with_text_values(): void
    {
        foreach (TranslationKeys::dictionary('ar') as $english => $arabic) {
            $this->assertIsString($arabic, $english);
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Support\TranslationKeys;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Lists the interface strings with no translation yet (I18N-02), as a JSON
 * object ready to fill in and merge into lang/<locale>.json.
 */
#[Signature('i18n:missing {locale=ar : The dictionary to check} {--count : Only print how many are missing}')]
#[Description('List interface strings that have no translation in lang/<locale>.json')]
class MissingTranslations extends Command
{
    public function handle(): int
    {
        $missing = TranslationKeys::missing((string) $this->argument('locale'));

        if ($this->option('count')) {
            $this->line((string) count($missing));

            return self::SUCCESS;
        }

        $this->line((string) json_encode(
            array_fill_keys($missing, ''),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));

        return self::SUCCESS;
    }
}

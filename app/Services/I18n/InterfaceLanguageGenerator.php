<?php

namespace App\Services\I18n;

use App\Contracts\AiProvider;
use App\Enums\AiFeature;
use App\Models\InterfaceLanguage;
use App\Models\User;
use App\Services\Ai\UsageMeter;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Translates the interface into an added language with AI, a few batches
 * per call (client request 2026-10-03). The Settings page calls it again
 * until nothing is left, so no queue worker is needed and a closed page
 * simply resumes where it stopped. A string already translated — by AI or
 * by hand — is never sent again.
 */
final class InterfaceLanguageGenerator
{
    private const int BATCH = 60;

    public function __construct(
        private readonly AiProvider $ai,
        private readonly UsageMeter $meter,
    ) {}

    /** How many strings still have no translation. */
    public function remaining(InterfaceLanguage $language): int
    {
        return count($this->missing($language));
    }

    /**
     * Translate for up to `$seconds` seconds; returns how many are left.
     */
    public function step(InterfaceLanguage $language, User $actor, int $seconds = 20): int
    {
        @set_time_limit(max(60, $seconds * 4));
        $started = microtime(true);
        $language->forceFill(['status' => 'generating', 'failed_reason' => null])->save();

        try {
            do {
                $missing = $this->missing($language);

                if ($missing === []) {
                    break;
                }

                $batch = array_slice($missing, 0, self::BATCH, true);
                $ids = [];
                $strings = [];

                // Short ids keep the prompt small; a letter keeps PHP from
                // turning them into list indexes.
                foreach (array_keys($batch) as $index => $key) {
                    $ids['s'.$index] = $key;
                    $strings['s'.$index] = $batch[$key];
                }

                $draft = $this->ai->translateInterfaceStrings($strings, $language->name);
                $this->meter->record($actor, AiFeature::Translation, $draft->usage, chargePoints: false);

                $messages = $language->messages ?? [];
                $added = 0;

                foreach ($draft->texts as $id => $text) {
                    $key = $ids[$id] ?? null;

                    if ($key !== null && self::keepsPlaceholders($batch[$key], $text)) {
                        $messages[$key] = $text;
                        $added++;
                    }
                }

                // A batch the AI answered with nothing usable would loop
                // forever: stop and say so.
                if ($added === 0) {
                    throw new \RuntimeException(__('The AI returned no usable translations.'));
                }

                $language->forceFill(['messages' => $messages])->save();
            } while (microtime(true) - $started < $seconds);
        } catch (Throwable $e) {
            $reason = $e instanceof RequestException && $e->response->status() === 429
                ? __('The AI service is busy or out of quota. Try again later.')
                : $e->getMessage();
            $language->forceFill(['status' => 'failed', 'failed_reason' => Str::limit($reason, 250)])->save();

            return $this->remaining($language);
        }

        $left = $this->remaining($language);
        $language->forceFill(['status' => $left === 0 ? 'ready' : 'generating'])->save();

        return $left;
    }

    /**
     * The source strings with no translation yet, key → English text.
     *
     * @return array<string, string>
     */
    private function missing(InterfaceLanguage $language): array
    {
        $done = $language->translations();

        return array_filter(
            InterfaceLanguages::sourceStrings(),
            fn (string $english, string $key): bool => ! isset($done[$key]),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /** Every `:placeholder` of the English text is still in the translation. */
    public static function keepsPlaceholders(string $english, string $translation): bool
    {
        preg_match_all('/:[a-z_]+/i', $english, $matches);

        foreach (array_unique($matches[0]) as $placeholder) {
            if (! str_contains($translation, $placeholder)) {
                return false;
            }
        }

        return true;
    }
}

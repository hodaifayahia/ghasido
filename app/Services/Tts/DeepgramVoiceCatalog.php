<?php

namespace App\Services\Tts;

/**
 * The Flux voice metadata shown in the admin (TTS-04, API-04).
 *
 * The values mirror Deepgram's official Flux TTS voice catalog. Keeping the
 * catalog in one server-side class means the dashboard, scenario editor and
 * lesson builder never drift or accept a voice model that was not offered to
 * the administrator.
 */
/**
 * @phpstan-type Voice array{model: string, name: string, gender: string, accent: string, age: string, characteristics: list<string>, use_cases: list<string>, featured: bool}
 */
final class DeepgramVoiceCatalog
{
    /**
     * @return list<Voice>
     */
    public static function all(): array
    {
        return [
            self::voice('hannah', 'Hannah', 'Feminine', 'American', 'Young', ['Clear', 'Confident', 'Thoughtful', 'Pleasant', 'Nice'], ['Casual Chat', 'Storytelling'], true),
            self::voice('kit', 'Kit', 'Masculine', 'British', 'Young Adult', ['Friendly', 'Energetic', 'Thoughtful', 'Calm', 'Helpful'], ['Customer Service', 'Narration', 'Financial Services'], true),
            self::voice('alexis', 'Alexis', 'Feminine', 'American', 'Adult', ['Clear', 'Professional', 'Calm', 'Caring', 'Empathetic'], ['Customer Service', 'IVR', 'Financial Services'], true),
            self::voice('cliff', 'Cliff', 'Masculine', 'American', 'Mature', ['Deep', 'Confident', 'Calm', 'Raspy', 'Clear'], ['Financial Services', 'Narration', 'Customer Service'], true),
            self::voice('sienna', 'Sienna', 'Feminine', 'American', 'Young Adult', ['Clear', 'Professional', 'Calm', 'Warm', 'Caring'], ['Customer Service', 'Financial Services', 'Narration'], true),
            self::voice('cole', 'Cole', 'Masculine', 'American', 'Young', ['Friendly', 'Clear', 'Interesting', 'Energetic', 'Engaging'], ['Customer Service', 'IVR'], true),
            self::voice('brooke', 'Brooke', 'Feminine', 'American', 'Young', ['Friendly', 'Intelligent', 'Fast', 'Confident', 'Energetic'], ['Healthcare', 'Financial Services', 'Casual Chat'], true),
            self::voice('colin', 'Colin', 'Masculine', 'British', 'Adult', ['Warm', 'Friendly', 'Trustworthy', 'Confident', 'Authoritative'], ['Customer Service', 'Financial Services', 'Narration'], true),
            self::voice('gemma', 'Gemma', 'Feminine', 'British', 'Young', ['Friendly', 'Kind', 'Approachable', 'Caring', 'Happy'], ['Customer Service', 'IVR'], true),
            self::voice('haley', 'Haley', 'Feminine', 'American', 'Young Adult', ['Clear', 'Professional', 'Caring', 'Calm', 'Empathetic'], ['Customer Service', 'Financial Services', 'IVR'], true),
            self::voice('heather', 'Heather', 'Feminine', 'American', 'Young', ['Clear', 'Engaging', 'Energetic', 'Friendly', 'Thoughtful'], ['Customer Service', 'IVR'], true),
            self::voice('miles', 'Miles', 'Masculine', 'American', 'Adult', ['Clear', 'Calm', 'Professional', 'Confident', 'Sincere'], ['Customer Service', 'Financial Services', 'Informative Narration'], true),
            self::voice('sean', 'Sean', 'Masculine', 'British', 'Mature', ['Friendly', 'Kind', 'Caring', 'Calming'], ['IVR'], true),
            self::voice('bree', 'Bree', 'Feminine', 'American', 'Mature', ['Friendly', 'Sweet', 'Kind'], ['Customer Service', 'Casual Chat']),
            self::voice('brittany', 'Brittany', 'Feminine', 'American', 'Mature', ['Confident', 'Kind', 'Soft'], ['Casual Chat']),
            self::voice('bruce', 'Bruce', 'Masculine', 'American', 'Adult', ['Friendly', 'Kind', 'Natural', 'Believable', 'Engaged'], ['Customer Service', 'IVR']),
            self::voice('conor', 'Conor', 'Masculine', 'British', 'Mature', ['Confident', 'Deep', 'Friendly', 'Relaxed'], ['Customer Service', 'IVR']),
            self::voice('donovan', 'Donovan', 'Masculine', 'American', 'Adult', ['Professional', 'Calm', 'Thoughtful'], ['IVR']),
            self::voice('drew', 'Drew', 'Masculine', 'American', 'Adult', ['Confident', 'Relaxed', 'Soft', 'Young', 'Calm'], ['Healthcare', 'Financial Services', 'Customer Service', 'IVR']),
            self::voice('elise', 'Elise', 'Feminine', 'American', 'Adult', ['Clear', 'Professional', 'Calm', 'Caring', 'Empathetic'], ['Customer Service', 'Financial Services', 'IVR']),
            self::voice('jack', 'Jack', 'Masculine', 'British', 'Adult', ['Confident', 'Thoughtful', 'Friendly', 'Professional', 'Clear'], ['Customer Service', 'Storytelling']),
            self::voice('kai', 'Kai', 'Masculine', 'Singaporean', 'Young Adult', ['Clear', 'Calm', 'Professional', 'Knowledgeable', 'Caring'], ['Customer Service', 'Informative Narration', 'IVR']),
            self::voice('kelsey', 'Kelsey', 'Feminine', 'American', 'Young Adult', ['Clear', 'Professional', 'Caring', 'Calm', 'Empathetic'], ['Customer Service', 'IVR', 'Financial Services']),
            self::voice('maeve', 'Maeve', 'Feminine', 'Irish', 'Adult', ['Friendly', 'Energetic', 'Confident', 'Gentle', 'Calm'], ['Customer Service', 'IVR', 'Narration']),
            self::voice('marcelo', 'Marcelo', 'Masculine', 'Filipino', 'Young Adult', ['Clear', 'Calm', 'Professional', 'Knowledgeable', 'Caring'], ['Customer Service', 'Informative Narration', 'IVR']),
            self::voice('marcus', 'Marcus', 'Masculine', 'American', 'Adult', ['Friendly', 'Helpful', 'Smooth', 'Professional', 'Kind'], ['Customer Service', 'Casual Chat']),
            self::voice('meena', 'Meena', 'Feminine', 'Indian', 'Adult', ['Empathetic', 'Professional', 'Calm', 'Reassuring', 'Satisfying'], ['Customer Service', 'Casual Chat']),
            self::voice('meghan', 'Meghan', 'Feminine', 'American', 'Adult', ['Friendly', 'Nice', 'Energetic', 'Kind', 'Confident'], ['Healthcare', 'Financial Services']),
            self::voice('naveen', 'Naveen', 'Masculine', 'Indian', 'Adult', ['Clear', 'Professional', 'Knowledgeable', 'Calm', 'Caring'], ['Customer Service', 'IVR', 'Informative Narration']),
            self::voice('paige', 'Paige', 'Feminine', 'American', 'Young Adult', ['Clear', 'Professional', 'Calm', 'Comfortable', 'Caring'], ['Customer Service', 'Financial Services', 'IVR']),
            self::voice('priya', 'Priya', 'Feminine', 'Indian', 'Adult', ['Confident', 'Empathetic', 'Professional', 'Calm', 'Reassuring'], ['IVR']),
            self::voice('rufus', 'Rufus', 'Masculine', 'British', 'Adult', ['Friendly', 'Confident', 'Intelligent', 'Gentle', 'Enthusiastic'], ['Healthcare', 'Financial Services', 'Storytelling']),
            self::voice('sharon', 'Sharon', 'Feminine', 'Australian', 'Young', ['Formal', 'Calm', 'Relaxed', 'Confident'], ['Healthcare', 'Financial Services']),
            self::voice('tanner', 'Tanner', 'Masculine', 'British', 'Adult', ['Professional', 'Calm', 'Confident'], ['Customer Service']),
            self::voice('wade', 'Wade', 'Masculine', 'American', 'Adult', ['Warm', 'Confident', 'Clear', 'Enthusiastic', 'Friendly'], ['Customer Service', 'Casual Chat']),
            self::voice('wes', 'Wes', 'Masculine', 'American', 'Adult', ['Thoughtful', 'Friendly', 'Warm', 'Interesting'], ['Customer Service', 'Casual Chat']),
        ];
    }

    /** @return list<string> */
    public static function models(): array
    {
        return array_column(self::all(), 'model');
    }

    /**
     * The catalog accent of one voice model ("British", "American"…), or
     * null for a model the catalog does not list.
     */
    public static function accentOf(string $model): ?string
    {
        foreach (self::all() as $voice) {
            if ($voice['model'] === $model) {
                return $voice['accent'];
            }
        }

        return null;
    }

    /**
     * The voice models of one catalog accent, for the per-accent pickers.
     *
     * @return list<string>
     */
    public static function modelsWithAccent(string $accent): array
    {
        return array_values(array_map(
            static fn (array $voice): string => $voice['model'],
            array_filter(self::all(), static fn (array $voice): bool => $voice['accent'] === $accent),
        ));
    }

    /** @return array{genders: list<string>, accents: list<string>, ages: list<string>, use_cases: list<string>, characteristics: list<string>} */
    public static function filters(): array
    {
        $voices = self::all();

        return [
            'genders' => ['Feminine', 'Masculine'],
            'accents' => ['American', 'Australian', 'British', 'Filipino', 'Indian', 'Irish', 'Singaporean'],
            'ages' => ['Adult', 'Mature', 'Young Adult', 'Young'],
            'use_cases' => self::uniqueValues($voices, 'use_cases'),
            'characteristics' => self::uniqueValues($voices, 'characteristics'),
        ];
    }

    /**
     * @param  list<string>  $characteristics
     * @param  list<string>  $useCases
     * @return Voice
     */
    private static function voice(string $id, string $name, string $gender, string $accent, string $age, array $characteristics, array $useCases, bool $featured = false): array
    {
        return [
            'model' => 'flux-'.$id.'-en',
            'name' => $name,
            'gender' => $gender,
            'accent' => $accent,
            'age' => $age,
            'characteristics' => $characteristics,
            'use_cases' => $useCases,
            'featured' => $featured,
        ];
    }

    /**
     * @param  list<Voice>  $voices
     * @param  'use_cases'|'characteristics'  $key
     * @return list<string>
     */
    private static function uniqueValues(array $voices, string $key): array
    {
        /** @var array<string, true> $values */
        $values = [];

        foreach ($voices as $voice) {
            foreach ($voice[$key] as $value) {
                $values[$value] = true;
            }
        }

        $result = [];
        foreach (array_keys($values) as $value) {
            $result[] = $value;
        }
        sort($result);

        return $result;
    }
}

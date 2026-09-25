<?php

namespace App\Services\Pronunciation;

/**
 * How two spoken words are compared (spec 0006 §5): one lowercase key per
 * word, equal when a listener would hear the same word.
 *
 * "The same word" is wider than equal spelling: British and American
 * spellings of one word (colour / color, centre / center), common homophones
 * (suite / sweet), and the recogniser's own spelling of a contraction all
 * count as the same. "Close" is a phonetic judgement: PHP's metaphone key
 * plus plain spelling distance, so "very" heard as "furry" or "think" heard
 * as "sync" is a mispronunciation, not a different word. No dictionary and
 * no package: the probe (spec 0006 §2) showed this is enough to sort the
 * recogniser's output.
 */
final class SpokenWords
{
    /** Hesitation sounds Deepgram returns with `filler_words=true`. */
    public const FILLERS = ['uh', 'um', 'uhm', 'umm', 'uh-huh', 'er', 'erm', 'ah', 'eh', 'hmm', 'mm', 'mhm'];

    /**
     * Contractions and what they stand for, so "I'm" said as "I am" (or the
     * reverse) is not a missing word (spec 0006 §5).
     *
     * @var array<string, string>
     */
    public const CONTRACTIONS = [
        "i'm" => 'i am', "you're" => 'you are', "we're" => 'we are', "they're" => 'they are',
        "it's" => 'it is', "that's" => 'that is', "there's" => 'there is', "here's" => 'here is',
        "what's" => 'what is', "where's" => 'where is', "who's" => 'who is', "how's" => 'how is',
        "he's" => 'he is', "she's" => 'she is', "let's" => 'let us',
        "don't" => 'do not', "doesn't" => 'does not', "didn't" => 'did not', "can't" => 'cannot',
        "won't" => 'will not', "isn't" => 'is not', "aren't" => 'are not', "wasn't" => 'was not',
        "weren't" => 'were not', "haven't" => 'have not', "hasn't" => 'has not', "hadn't" => 'had not',
        "wouldn't" => 'would not', "couldn't" => 'could not', "shouldn't" => 'should not',
        "i'll" => 'i will', "you'll" => 'you will', "we'll" => 'we will', "they'll" => 'they will',
        "it'll" => 'it will', "he'll" => 'he will', "she'll" => 'she will',
        "i've" => 'i have', "you've" => 'you have', "we've" => 'we have', "they've" => 'they have',
        "i'd" => 'i would', "you'd" => 'you would', "we'd" => 'we would', "they'd" => 'they would',
    ];

    /**
     * One word, many spellings: British → American (the recogniser writes
     * American), abbreviations the recogniser spells out, and variants.
     *
     * @var array<string, string>
     */
    private const SPELLINGS = [
        'centre' => 'center', 'centres' => 'centers', 'theatre' => 'theater', 'metre' => 'meter',
        'metres' => 'meters', 'litre' => 'liter', 'litres' => 'liters', 'fibre' => 'fiber',
        'travelled' => 'traveled', 'travelling' => 'traveling', 'traveller' => 'traveler',
        'travellers' => 'travelers', 'cancelled' => 'canceled', 'cancelling' => 'canceling',
        'labelled' => 'labeled', 'jewellery' => 'jewelry', 'fuelled' => 'fueled',
        'programme' => 'program', 'programmes' => 'programs', 'cheque' => 'check', 'cheques' => 'checks',
        'grey' => 'gray', 'catalogue' => 'catalog', 'dialogue' => 'dialog', 'licence' => 'license',
        'defence' => 'defense', 'offence' => 'offense', 'tyre' => 'tire', 'tyres' => 'tires',
        'aluminium' => 'aluminum', 'pyjamas' => 'pajamas', 'enquiry' => 'inquiry',
        'enquiries' => 'inquiries', 'enquire' => 'inquire', 'practise' => 'practice',
        'ok' => 'okay', 'mr' => 'mister', 'mrs' => 'missus', 'dr' => 'doctor',
        'wi-fi' => 'wifi', 'e-mail' => 'email', 'towards' => 'toward',
    ];

    /** British "-our" stems whose American spelling is "-or". */
    private const OUR_STEMS = 'colo|favo|hono|neighbo|behavio|flavo|labo|humo|harbo|rumo|savo|parlo|odo|vapo|armo|endeavo';

    /** British "-ise" stems whose American spelling is "-ize". */
    private const ISE_STEMS = 'apologi|organi|reali|recogni|speciali|prioriti|finali|minimi|maximi|customi|personali|summari|memori|categori|authori|emphasi|critici|sympathi|standardi|utili|familiari|characteri|optimi|centrali|visuali|normali';

    /**
     * Groups of words a listener cannot tell apart in either accent.
     *
     * @var list<list<string>>
     */
    private const HOMOPHONES = [
        ['for', 'four', 'fore'], ['to', 'too', 'two'], ['there', 'their', "they're"],
        ['right', 'write', 'rite'], ['hear', 'here'], ['by', 'buy', 'bye'], ['no', 'know'],
        ['one', 'won'], ['our', 'hour'], ['meet', 'meat'], ['week', 'weak'], ['sweet', 'suite'],
        ['stair', 'stare'], ['fair', 'fare'], ['break', 'brake'], ['pair', 'pear'],
        ['sale', 'sail'], ['wait', 'weight'], ['board', 'bored'], ['some', 'sum'],
        ['whole', 'hole'], ['new', 'knew'], ['see', 'sea'], ['i', 'eye'], ['would', 'wood'],
        ['in', 'inn'], ['tea', 'tee'], ['hi', 'high'], ['its', "it's"], ['your', "you're"],
        ['wear', 'where'], ['weather', 'whether'], ['way', 'weigh'], ['piece', 'peace'],
        ['plain', 'plane'], ['road', 'rode'], ['son', 'sun'], ['flour', 'flower'],
        ['mail', 'male'], ['night', 'knight'], ['not', 'knot'], ['rain', 'reign'],
        ['cell', 'sell'], ['dear', 'deer'], ['due', 'dew'], ['hair', 'hare'], ['check', 'cheque'],
        ['ate', 'eight'], ['be', 'bee'], ['great', 'grate'], ['know', 'no'], ['mind', 'mined'],
        ['okay', 'ok'], ['alright', 'all right'],
    ];

    /**
     * The key a word is compared by: lowercase, curly apostrophes made
     * straight, punctuation trimmed, one spelling for British/American
     * variants.
     */
    public static function key(string $word): string
    {
        $word = mb_strtolower(trim($word));
        $word = str_replace(['’', '‘', '`', '´'], "'", $word);
        $word = preg_replace("/[^\\p{L}\\p{N}'\\-]+/u", '', $word) ?? $word;
        $word = trim($word, "'-");

        return self::canonical($word);
    }

    /**
     * American spelling of a British variant; anything else unchanged.
     */
    public static function canonical(string $key): string
    {
        if (isset(self::SPELLINGS[$key])) {
            return self::SPELLINGS[$key];
        }

        $key = preg_replace('/^('.self::OUR_STEMS.')ur(s|ed|ing|ite|ites|able|ful|er|ers)?$/', '$1r$2', $key) ?? $key;

        return preg_replace('/^('.self::ISE_STEMS.')s(e|es|ed|ing|ation|ations|er|ers)$/', '$1z$2', $key) ?? $key;
    }

    /**
     * Would a listener hear these as the same word? Equal keys, homophones,
     * or a homophone the pronunciation guide names for this word.
     *
     * @param  list<string>  $extraHomophones
     */
    public static function same(string $reference, string $heard, array $extraHomophones = []): bool
    {
        if ($reference === $heard) {
            return true;
        }

        if (in_array($heard, $extraHomophones, true)) {
            return true;
        }

        foreach (self::HOMOPHONES as $group) {
            if (in_array($reference, $group, true) && in_array($heard, $group, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * How alike two different words sound, 0-1: the metaphone keys weigh
     * 70 %, the spelling 30 %. very/furry 0.82, think/sync 0.53,
     * bill/pill 0.58, room/sim 0.43.
     */
    public static function similarity(string $reference, string $heard): float
    {
        if ($reference === $heard) {
            return 1.0;
        }

        $a = metaphone($reference);
        $b = metaphone($heard);

        $phonetic = 1 - levenshtein($a, $b) / max(strlen($a), strlen($b), 1);
        $spelling = 1 - levenshtein($reference, $heard) / max(strlen($reference), strlen($heard), 1);

        return round(max(0.0, 0.7 * $phonetic + 0.3 * $spelling), 3);
    }

    public static function isFiller(string $key): bool
    {
        return in_array($key, self::FILLERS, true);
    }

    /**
     * A token that is a number or holds digits ("214", "3pm"): not judged,
     * because the recogniser writes numbers in more than one way.
     */
    public static function isNumeric(string $key): bool
    {
        return preg_match('/\d/', $key) === 1;
    }

    /**
     * A number said as words ("two", "fourteen", "oh"): how the recogniser
     * writes a spoken "214", so it is never an extra word next to a number.
     */
    public static function isNumberWord(string $key): bool
    {
        return preg_match('/^(zero|oh|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty|thirty|forty|fifty|sixty|seventy|eighty|ninety|hundred|thousand|and|a|am|pm|o\'clock|first|second|third|fourth|fifth|th|st|nd|rd)$/', $key) === 1;
    }

    public static function expansion(string $key): ?string
    {
        return self::CONTRACTIONS[$key] ?? null;
    }

    public static function contraction(string $expanded): ?string
    {
        $found = array_search($expanded, self::CONTRACTIONS, true);

        return is_string($found) ? $found : null;
    }
}

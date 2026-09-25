<?php

namespace App\Services\Pronunciation;

use App\Contracts\TranscribedWord;
use App\Contracts\WordTranscript;

/**
 * Turns what the recogniser heard into a verdict per word and three scores
 * (spec 0006 §5, "Score v1"). Pure: no database, no network, no clock, so
 * the same transcripts always give the same result and a stored attempt can
 * be re-scored later (DATA-01, AIE-04).
 *
 * Thresholds and weights come from `guesvia.pronunciation` and are stored
 * with every attempt through `version`.
 *
 * @phpstan-import-type WordResult from PronunciationOutcome
 * @phpstan-import-type Extra from PronunciationOutcome
 *
 * @phpstan-type HeardToken array{key: string, word: string, confidence: float|null, start_ms: int|null, end_ms: int|null}
 */
final class PronunciationScorer
{
    /**
     * @var array{version: string, weights: array{words: float, clarity: float, flow: float}, credit: array<string, float>, close_similarity: float, unclear_floor: float, unclear_margin: float, default_reference_confidence: float, hinted_min_confidence: float, pause_ms: int, ms_per_word: int, slow_ratio: float, penalties: array{slow_per_ratio: float, slow_max: float, pause: float, pause_max: float, filler: float, filler_max: float, extra: float, extra_max: float}, levels: array{excellent: float, good: float, fair: float}}
     */
    private array $settings;

    /**
     * @param  array<string, mixed>|null  $settings  defaults to config('guesvia.pronunciation')
     */
    public function __construct(
        private readonly WordAligner $aligner = new WordAligner,
        ?array $settings = null,
    ) {
        $settings ??= (array) config('guesvia.pronunciation', []);

        $this->settings = self::withDefaults($settings);
    }

    public function version(): string
    {
        return $this->settings['version'];
    }

    /**
     * Judge one recording. `$hinted` is the second listen with the reference
     * words as keyterms; pass null for the first pass (the caller only runs
     * a second listen when needsHintedListen() says it can help).
     */
    public function score(
        ReferenceText $reference,
        WordTranscript $free,
        ?WordTranscript $hinted = null,
        ?PronunciationKnowledge $knowledge = null,
    ): PronunciationOutcome {
        $knowledge ??= PronunciationKnowledge::none();
        $fillers = 0;
        $heard = $this->heardTokens($reference, $free, $fillers);

        if ($heard === []) {
            return $this->nothingHeard($reference, $knowledge, $fillers);
        }

        [$words, $extras] = $this->firstListen($reference, $heard, $knowledge);

        if ($hinted !== null) {
            $words = $this->secondListen($reference, $words, $hinted, $knowledge);
        }

        $wordsScore = $this->wordsScore($words);
        $clarity = $free->hasConfidence() ? $this->clarityScore($words) : null;
        [$flow, $pauses, $ratio] = $this->flowScore($reference, $heard, $extras, $fillers, $knowledge);

        $score = $this->overall($wordsScore, $clarity, $flow);

        return new PronunciationOutcome(
            words: $words,
            extras: $extras,
            fillers: $fillers,
            longPauses: $pauses,
            speedRatio: $ratio,
            score: $score,
            wordsScore: $wordsScore,
            clarityScore: $clarity,
            flowScore: $flow,
            level: $this->level($score, $this->everyWordUnderstood($words)),
            heardAnything: true,
            version: $this->settings['version'],
        );
    }

    // ------------------------------------------------------------ listening

    /**
     * The recogniser's words as comparable tokens: fillers counted and
     * dropped, contractions split or joined to match how the reference
     * writes them, a run-together compound ("checkin") split.
     *
     * @return list<HeardToken>
     */
    private function heardTokens(ReferenceText $reference, WordTranscript $transcript, int &$fillers): array
    {
        $referenceKeys = $reference->keys();
        $referenceText = ' '.implode(' ', $referenceKeys).' ';
        $tokens = [];

        foreach ($transcript->words as $word) {
            $key = SpokenWords::key($word->word);

            if ($key === '') {
                continue;
            }

            if (SpokenWords::isFiller($key)) {
                $fillers++;

                continue;
            }

            foreach ($this->splitForReference($key, $referenceKeys, $referenceText) as $part) {
                $tokens[] = $this->token($part, $word);
            }
        }

        return $this->joinContractions($tokens, $referenceKeys, $referenceText);
    }

    /**
     * @param  list<string>  $referenceKeys
     * @return list<string>
     */
    private function splitForReference(string $key, array $referenceKeys, string $referenceText): array
    {
        if (in_array($key, $referenceKeys, true)) {
            return [$key];
        }

        // "I'm" heard where the reference writes "I am".
        $expansion = SpokenWords::expansion($key);
        if ($expansion !== null && str_contains($referenceText, ' '.$expansion.' ')) {
            return explode(' ', $expansion);
        }

        // "checkin" heard where the reference writes "check-in".
        for ($i = 0; $i < count($referenceKeys) - 1; $i++) {
            if ($key === $referenceKeys[$i].$referenceKeys[$i + 1]) {
                return [$referenceKeys[$i], $referenceKeys[$i + 1]];
            }
        }

        return [$key];
    }

    /**
     * "I am" heard where the reference writes "I'm".
     *
     * @param  list<HeardToken>  $tokens
     * @param  list<string>  $referenceKeys
     * @return list<HeardToken>
     */
    private function joinContractions(array $tokens, array $referenceKeys, string $referenceText): array
    {
        $joined = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $next = $tokens[$i + 1] ?? null;

            if ($next !== null) {
                $pair = $tokens[$i]['key'].' '.$next['key'];
                $contraction = SpokenWords::contraction($pair);

                if (
                    $contraction !== null
                    && in_array($contraction, $referenceKeys, true)
                    && ! str_contains($referenceText, ' '.$pair.' ')
                ) {
                    $joined[] = [
                        'key' => $contraction,
                        'word' => $tokens[$i]['word'].' '.$next['word'],
                        'confidence' => self::minConfidence($tokens[$i]['confidence'], $next['confidence']),
                        'start_ms' => $tokens[$i]['start_ms'],
                        'end_ms' => $next['end_ms'],
                    ];
                    $i++;

                    continue;
                }
            }

            $joined[] = $tokens[$i];
        }

        return $joined;
    }

    /**
     * @return HeardToken
     */
    private function token(string $key, TranscribedWord $word): array
    {
        return [
            'key' => $key,
            'word' => $word->word,
            'confidence' => $word->confidence,
            'start_ms' => $word->startMs,
            'end_ms' => $word->endMs,
        ];
    }

    /**
     * @param  list<HeardToken>  $heard
     * @return array{0: list<WordResult>, 1: list<Extra>}
     */
    private function firstListen(ReferenceText $reference, array $heard, PronunciationKnowledge $knowledge): array
    {
        $extras = [];
        $ops = $this->align($reference, $heard, $knowledge);
        $median = $this->medianMatchedConfidence($ops, $heard);
        $hasNumbers = in_array(false, array_column($reference->tokens, 'judged'), true);

        /** @var array<int, WordResult> $words */
        $words = [];

        foreach ($ops as $op) {
            if ($op['ref'] === null) {
                $token = $heard[(int) $op['heard']];

                // "Room 214" said as "room two fourteen": the spoken number
                // is not an extra word.
                if (! ($hasNumbers && SpokenWords::isNumberWord($token['key']))) {
                    $extras[] = ['word' => $token['word'], 'confidence' => $token['confidence'], 'start_ms' => $token['start_ms']];
                }

                continue;
            }

            $index = $op['ref'];
            $ref = $reference->tokens[$index];
            $token = $op['heard'] === null ? null : $heard[$op['heard']];
            $referenceConfidence = $knowledge->referenceConfidence($index, $ref['key']);

            $word = [
                'index' => $index,
                'text' => $ref['display'],
                'key' => $ref['key'],
                'status' => PronunciationOutcome::MISSED,
                'heard' => null,
                'confidence' => $token['confidence'] ?? null,
                'reference_confidence' => $referenceConfidence,
                'start_ms' => $token['start_ms'] ?? null,
                'end_ms' => $token['end_ms'] ?? null,
                'sound' => null,
                'tip' => null,
                'trap' => false,
            ];

            if ($op['op'] === WordAligner::MATCH && $token !== null) {
                $word['status'] = $this->isUnclear($token['confidence'], $referenceConfidence, $median)
                    ? PronunciationOutcome::UNCLEAR
                    : PronunciationOutcome::CORRECT;
            } elseif ($op['op'] === WordAligner::SUBSTITUTE && $token !== null) {
                $word['heard'] = $token['word'];
                $trap = $knowledge->trapFor($ref['key'], $token['key']);

                if ($trap !== null) {
                    $word['status'] = PronunciationOutcome::MISPRONOUNCED;
                    $word['trap'] = true;
                    $word['sound'] = $trap['sound'] !== '' ? $trap['sound'] : SoundDiff::explain($ref['key'], $token['key']);
                    $word['tip'] = $trap['tip'] !== '' ? $trap['tip'] : null;
                } elseif ($op['similarity'] >= $this->settings['close_similarity']) {
                    $word['status'] = PronunciationOutcome::MISPRONOUNCED;
                    $word['sound'] = SoundDiff::explain($ref['key'], $token['key']);
                } else {
                    $word['status'] = PronunciationOutcome::DIFFERENT;
                }
            }

            if (! $ref['judged'] || $knowledge->referenceMissed($index, $ref['key'])) {
                $word['status'] = PronunciationOutcome::SKIPPED;
            }

            $words[$index] = $word;
        }

        ksort($words);

        return [array_values($words), $extras];
    }

    /**
     * A word the free listen missed but the hinted listen hears: the learner
     * was close — "almost" (spec 0006 §2 finding 5).
     *
     * @param  list<WordResult>  $words
     * @return list<WordResult>
     */
    private function secondListen(ReferenceText $reference, array $words, WordTranscript $hinted, PronunciationKnowledge $knowledge): array
    {
        $fillers = 0;
        $heard = $this->heardTokens($reference, $hinted, $fillers);

        if ($heard === []) {
            return $words;
        }

        $recovered = [];

        foreach ($this->align($reference, $heard, $knowledge) as $op) {
            if ($op['op'] !== WordAligner::MATCH || $op['ref'] === null || $op['heard'] === null) {
                continue;
            }

            $confidence = $heard[$op['heard']]['confidence'];

            if ($confidence === null || $confidence >= $this->settings['hinted_min_confidence']) {
                $recovered[$op['ref']] = true;
            }
        }

        foreach ($words as $position => $word) {
            $recoverable = in_array($word['status'], [PronunciationOutcome::MISPRONOUNCED, PronunciationOutcome::DIFFERENT, PronunciationOutcome::MISSED], true)
                && ! $word['trap'];

            if ($recoverable && isset($recovered[$word['index']])) {
                $words[$position]['status'] = PronunciationOutcome::ALMOST;
            }
        }

        return $words;
    }

    /**
     * @param  list<HeardToken>  $heard
     * @return list<array{op: string, ref: int|null, heard: int|null, similarity: float}>
     */
    private function align(ReferenceText $reference, array $heard, PronunciationKnowledge $knowledge): array
    {
        $keys = $reference->keys();

        return $this->aligner->align(
            $keys,
            array_column($heard, 'key'),
            fn (int $index, string $heardKey): bool => SpokenWords::same($keys[$index], $heardKey, $knowledge->homophonesFor($keys[$index])),
        );
    }

    /**
     * Unclear = the right word, heard with clearly less confidence than the
     * reference audio earned (and than the rest of this recording). Where
     * the recogniser struggles even with the reference, only a drop below
     * the reference itself counts.
     */
    private function isUnclear(?float $confidence, ?float $reference, float $median): bool
    {
        if ($confidence === null) {
            return false;
        }

        $reference ??= $this->settings['default_reference_confidence'];
        $margin = $this->settings['unclear_margin'];

        if ($reference < $this->settings['unclear_floor'] + $margin) {
            return $confidence < $reference - $margin;
        }

        return $confidence < max($this->settings['unclear_floor'], min($reference, $median) - $margin);
    }

    /**
     * @param  list<array{op: string, ref: int|null, heard: int|null, similarity: float}>  $ops
     * @param  list<HeardToken>  $heard
     */
    private function medianMatchedConfidence(array $ops, array $heard): float
    {
        $values = [];

        foreach ($ops as $op) {
            if ($op['op'] === WordAligner::MATCH && $op['heard'] !== null && $heard[$op['heard']]['confidence'] !== null) {
                $values[] = (float) $heard[$op['heard']]['confidence'];
            }
        }

        if ($values === []) {
            return 1.0;
        }

        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    // -------------------------------------------------------------- scoring

    /**
     * @param  list<WordResult>  $words
     */
    private function wordsScore(array $words): ?float
    {
        $judged = 0;
        $credit = 0.0;

        foreach ($words as $word) {
            if ($word['status'] === PronunciationOutcome::SKIPPED) {
                continue;
            }

            $judged++;
            $credit += $this->settings['credit'][$word['status']] ?? 0.0;
        }

        return $judged === 0 ? null : round(100 * $credit / $judged, 2);
    }

    /**
     * @param  list<WordResult>  $words
     */
    private function clarityScore(array $words): float
    {
        $ratios = [];

        foreach ($words as $word) {
            if (! in_array($word['status'], [PronunciationOutcome::CORRECT, PronunciationOutcome::UNCLEAR], true)) {
                continue;
            }

            if ($word['confidence'] === null) {
                continue;
            }

            $reference = max($word['reference_confidence'] ?? $this->settings['default_reference_confidence'], 0.5);
            $ratios[] = min(1.0, $word['confidence'] / $reference);
        }

        return $ratios === [] ? 0.0 : round(100 * array_sum($ratios) / count($ratios), 2);
    }

    /**
     * 100 minus penalties for speaking much slower than the reference, long
     * pauses, hesitations and extra words; null without word timings.
     *
     * @param  list<HeardToken>  $heard
     * @param  list<Extra>  $extras
     * @return array{0: float|null, 1: int, 2: float|null}
     */
    private function flowScore(ReferenceText $reference, array $heard, array $extras, int $fillers, PronunciationKnowledge $knowledge): array
    {
        $timed = array_values(array_filter(
            $heard,
            static fn (array $token): bool => $token['start_ms'] !== null && $token['end_ms'] !== null,
        ));

        if ($timed === []) {
            return [null, 0, null];
        }

        usort($timed, static fn (array $a, array $b): int => $a['start_ms'] <=> $b['start_ms']);

        $pauses = 0;
        for ($i = 1; $i < count($timed); $i++) {
            if ((int) $timed[$i]['start_ms'] - (int) $timed[$i - 1]['end_ms'] > $this->settings['pause_ms']) {
                $pauses++;
            }
        }

        $span = max(1, (int) $timed[count($timed) - 1]['end_ms'] - (int) $timed[0]['start_ms']);
        $expected = $knowledge->referenceDurationMs ?? max(1, $reference->count() * $this->settings['ms_per_word']);
        $ratio = round($span / max(1, $expected), 2);

        $p = $this->settings['penalties'];
        $penalty = min($p['slow_max'], max(0.0, $ratio - $this->settings['slow_ratio']) * $p['slow_per_ratio'])
            + min($p['pause_max'], $pauses * $p['pause'])
            + min($p['filler_max'], $fillers * $p['filler'])
            + min($p['extra_max'], count($extras) * $p['extra']);

        return [round(max(0.0, 100 - $penalty), 2), $pauses, $ratio];
    }

    private function overall(?float $words, ?float $clarity, ?float $flow): ?float
    {
        $weights = $this->settings['weights'];
        $sum = 0.0;
        $weight = 0.0;

        foreach (['words' => $words, 'clarity' => $clarity, 'flow' => $flow] as $part => $value) {
            if ($value !== null) {
                $sum += $value * $weights[$part];
                $weight += $weights[$part];
            }
        }

        return $weight > 0 ? round($sum / $weight, 2) : null;
    }

    /**
     * Excellent and Good need every judged word understood: a sentence a
     * guest would mishear ("a furry quiet room") is at best "fair", however
     * clear and fluent the rest was (spec 0006 §1).
     */
    private function level(?float $score, bool $understood = true): string
    {
        $levels = $this->settings['levels'];

        $level = match (true) {
            $score === null => PronunciationOutcome::LEVEL_NOT_HEARD,
            $score >= $levels['excellent'] => 'excellent',
            $score >= $levels['good'] => 'good',
            $score >= $levels['fair'] => 'fair',
            default => 'try_again',
        };

        return ! $understood && in_array($level, ['excellent', 'good'], true) ? 'fair' : $level;
    }

    /**
     * No judged word was misheard, heard as another word or missed.
     *
     * @param  list<WordResult>  $words
     */
    private function everyWordUnderstood(array $words): bool
    {
        foreach ($words as $word) {
            if (in_array($word['status'], [PronunciationOutcome::MISPRONOUNCED, PronunciationOutcome::DIFFERENT, PronunciationOutcome::MISSED], true)) {
                return false;
            }
        }

        return true;
    }

    private function nothingHeard(ReferenceText $reference, PronunciationKnowledge $knowledge, int $fillers): PronunciationOutcome
    {
        $words = [];

        foreach ($reference->tokens as $index => $token) {
            $words[] = [
                'index' => $index,
                'text' => $token['display'],
                'key' => $token['key'],
                'status' => $token['judged'] ? PronunciationOutcome::MISSED : PronunciationOutcome::SKIPPED,
                'heard' => null,
                'confidence' => null,
                'reference_confidence' => $knowledge->referenceConfidence($index, $token['key']),
                'start_ms' => null,
                'end_ms' => null,
                'sound' => null,
                'tip' => null,
                'trap' => false,
            ];
        }

        return new PronunciationOutcome(
            words: $words,
            extras: [],
            fillers: $fillers,
            longPauses: 0,
            speedRatio: null,
            score: 0.0,
            wordsScore: 0.0,
            clarityScore: null,
            flowScore: null,
            level: PronunciationOutcome::LEVEL_NOT_HEARD,
            heardAnything: false,
            version: $this->settings['version'],
        );
    }

    private static function minConfidence(?float $a, ?float $b): ?float
    {
        if ($a === null || $b === null) {
            return $a ?? $b;
        }

        return min($a, $b);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{version: string, weights: array{words: float, clarity: float, flow: float}, credit: array<string, float>, close_similarity: float, unclear_floor: float, unclear_margin: float, default_reference_confidence: float, hinted_min_confidence: float, pause_ms: int, ms_per_word: int, slow_ratio: float, penalties: array{slow_per_ratio: float, slow_max: float, pause: float, pause_max: float, filler: float, filler_max: float, extra: float, extra_max: float}, levels: array{excellent: float, good: float, fair: float}}
     */
    public static function withDefaults(array $settings): array
    {
        $defaults = self::defaults();
        $read = static function (string $key, float $default) use ($settings): float {
            $value = data_get($settings, $key);

            return is_numeric($value) ? (float) $value : $default;
        };

        $credit = [];
        foreach ($defaults['credit'] as $status => $value) {
            $credit[$status] = $read('credit.'.$status, $value);
        }

        $penalties = [];
        foreach ($defaults['penalties'] as $name => $value) {
            $penalties[$name] = $read('penalties.'.$name, $value);
        }

        $version = data_get($settings, 'version');

        return [
            'version' => is_string($version) && $version !== '' ? $version : $defaults['version'],
            'weights' => [
                'words' => $read('weights.words', $defaults['weights']['words']),
                'clarity' => $read('weights.clarity', $defaults['weights']['clarity']),
                'flow' => $read('weights.flow', $defaults['weights']['flow']),
            ],
            'credit' => $credit,
            'close_similarity' => $read('close_similarity', $defaults['close_similarity']),
            'unclear_floor' => $read('unclear_floor', $defaults['unclear_floor']),
            'unclear_margin' => $read('unclear_margin', $defaults['unclear_margin']),
            'default_reference_confidence' => $read('default_reference_confidence', $defaults['default_reference_confidence']),
            'hinted_min_confidence' => $read('hinted_min_confidence', $defaults['hinted_min_confidence']),
            'pause_ms' => (int) $read('pause_ms', $defaults['pause_ms']),
            'ms_per_word' => (int) $read('ms_per_word', $defaults['ms_per_word']),
            'slow_ratio' => $read('slow_ratio', $defaults['slow_ratio']),
            'penalties' => [
                'slow_per_ratio' => $penalties['slow_per_ratio'],
                'slow_max' => $penalties['slow_max'],
                'pause' => $penalties['pause'],
                'pause_max' => $penalties['pause_max'],
                'filler' => $penalties['filler'],
                'filler_max' => $penalties['filler_max'],
                'extra' => $penalties['extra'],
                'extra_max' => $penalties['extra_max'],
            ],
            'levels' => [
                'excellent' => $read('levels.excellent', $defaults['levels']['excellent']),
                'good' => $read('levels.good', $defaults['levels']['good']),
                'fair' => $read('levels.fair', $defaults['levels']['fair']),
            ],
        ];
    }

    /**
     * Score v1 (spec 0006 §5).
     *
     * @return array{version: string, weights: array{words: float, clarity: float, flow: float}, credit: array<string, float>, close_similarity: float, unclear_floor: float, unclear_margin: float, default_reference_confidence: float, hinted_min_confidence: float, pause_ms: int, ms_per_word: int, slow_ratio: float, penalties: array{slow_per_ratio: float, slow_max: float, pause: float, pause_max: float, filler: float, filler_max: float, extra: float, extra_max: float}, levels: array{excellent: float, good: float, fair: float}}
     */
    public static function defaults(): array
    {
        return [
            'version' => 'v1',
            'weights' => ['words' => 0.6, 'clarity' => 0.25, 'flow' => 0.15],
            'credit' => [
                PronunciationOutcome::CORRECT => 1.0,
                PronunciationOutcome::UNCLEAR => 0.7,
                PronunciationOutcome::ALMOST => 0.5,
                PronunciationOutcome::MISPRONOUNCED => 0.25,
                PronunciationOutcome::DIFFERENT => 0.0,
                PronunciationOutcome::MISSED => 0.0,
            ],
            'close_similarity' => 0.5,
            'unclear_floor' => 0.55,
            'unclear_margin' => 0.15,
            'default_reference_confidence' => 0.95,
            'hinted_min_confidence' => 0.3,
            'pause_ms' => 700,
            'ms_per_word' => 380,
            'slow_ratio' => 1.6,
            'penalties' => [
                'slow_per_ratio' => 40.0,
                'slow_max' => 40.0,
                'pause' => 10.0,
                'pause_max' => 30.0,
                'filler' => 8.0,
                'filler_max' => 24.0,
                'extra' => 5.0,
                'extra_max' => 20.0,
            ],
            'levels' => ['excellent' => 90.0, 'good' => 75.0, 'fair' => 50.0],
        ];
    }
}

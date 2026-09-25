<?php

namespace Tests\Unit\Pronunciation;

use App\Contracts\TranscribedWord;
use App\Contracts\WordTranscript;
use App\Services\Pronunciation\PronunciationKnowledge;
use App\Services\Pronunciation\PronunciationOutcome;
use App\Services\Pronunciation\PronunciationScorer;
use App\Services\Pronunciation\ReferenceText;
use App\Services\Pronunciation\WordAligner;
use PHPUnit\Framework\TestCase;

/**
 * Score v1 (spec 0006 §5): the verdict per word and the three scores, from
 * transcripts shaped like the 2026-09-24 Deepgram probe. No database, no
 * network, no config: the scorer is given the v1 defaults.
 */
class PronunciationScorerTest extends TestCase
{
    private PronunciationScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scorer = new PronunciationScorer(new WordAligner, PronunciationScorer::defaults());
    }

    /**
     * A transcript from "word:confidence" pairs, 300 ms per word, 100 ms
     * apart, so flow sees a natural pace.
     */
    private function heard(string $spoken, bool $withConfidence = true, int $gapMs = 100): WordTranscript
    {
        $words = [];
        $at = 0;

        foreach (preg_split('/\s+/', trim($spoken), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $entry) {
            [$word, $confidence] = array_pad(explode(':', $entry), 2, '0.98');
            $words[] = new TranscribedWord($word, $withConfidence ? (float) $confidence : null, $at, $at + 300);
            $at += 300 + $gapMs;
        }

        return new WordTranscript(implode(' ', array_map(fn (TranscribedWord $w): string => $w->word, $words)), $words, 'test', 'test');
    }

    /**
     * @return array<string, string>
     */
    private function statuses(PronunciationOutcome $outcome): array
    {
        $statuses = [];

        foreach ($outcome->words as $word) {
            $statuses[$word['text']] = $word['status'];
        }

        return $statuses;
    }

    public function test_a_clear_sentence_is_correct_word_for_word()
    {
        $outcome = $this->scorer->score(
            ReferenceText::from('Would you like a very quiet room?'),
            $this->heard('would you like a very quiet room'),
        );

        $this->assertSame(array_fill_keys(['Would', 'you', 'like', 'a', 'very', 'quiet', 'room'], 'correct'), $this->statuses($outcome));
        $this->assertTrue($outcome->isPerfect());
        $this->assertFalse($outcome->needsHintedListen());
        $this->assertSame('excellent', $outcome->level);
        $this->assertSame(100.0, $outcome->wordsScore);
        $this->assertSame(100.0, $outcome->flowScore);
        $this->assertGreaterThanOrEqual(90.0, $outcome->score);
    }

    public function test_very_heard_as_furry_is_a_v_to_f_mispronunciation()
    {
        $outcome = $this->scorer->score(
            ReferenceText::from('Would you like a very quiet room?'),
            $this->heard('would you like a furry quiet:0.5 room'),
        );

        $very = $outcome->words[4];

        $this->assertSame('mispronounced', $very['status']);
        $this->assertSame('furry', $very['heard']);
        $this->assertSame('v → f', $very['sound']);
        $this->assertFalse($very['trap']);
        $this->assertSame('unclear', $outcome->words[5]['status']);
        $this->assertTrue($outcome->needsHintedListen());
        // A guest would mishear the sentence: never better than "fair",
        // even with a high overall score.
        $this->assertGreaterThanOrEqual(75.0, $outcome->score);
        $this->assertSame('fair', $outcome->level);
        $this->assertFalse($outcome->isPerfect());
        $this->assertSame('very', $outcome->weakWords()[0]['text']);
    }

    public function test_a_trap_from_the_guide_names_the_sound_and_its_tip()
    {
        $knowledge = new PronunciationKnowledge(traps: [
            'very' => [['heard_as' => 'ferry', 'sound' => '/v/ → /f/', 'tip' => 'Touch your top teeth to your lip and hum.']],
        ]);

        $outcome = $this->scorer->score(
            ReferenceText::from('a very quiet room'),
            $this->heard('a ferry quiet room'),
            knowledge: $knowledge,
        );

        $very = $outcome->words[1];

        $this->assertSame('mispronounced', $very['status']);
        $this->assertTrue($very['trap']);
        $this->assertSame('/v/ → /f/', $very['sound']);
        $this->assertSame('Touch your top teeth to your lip and hum.', $very['tip']);
        // A named error is not re-listened for.
        $this->assertFalse($outcome->needsHintedListen());
    }

    public function test_a_word_only_heard_with_hints_is_almost()
    {
        $reference = ReferenceText::from('You are staying for three nights.');
        $free = $this->heard('you are staying for tree:0.77 nights');
        $hinted = $this->heard('you are staying for three:0.5 nights');

        $first = $this->scorer->score($reference, $free);
        $this->assertSame('mispronounced', $first->words[4]['status']);
        $this->assertSame('th → t', $first->words[4]['sound']);

        $second = $this->scorer->score($reference, $free, $hinted);
        $this->assertSame('almost', $second->words[4]['status']);
        $this->assertSame('tree', $second->words[4]['heard']);
        $this->assertGreaterThan($first->wordsScore, $second->wordsScore);
    }

    public function test_missing_and_extra_words_are_reported()
    {
        $outcome = $this->scorer->score(
            ReferenceText::from('Would you like a quiet room?'),
            $this->heard('would like a quiet room please'),
        );

        $this->assertSame('missed', $this->statuses($outcome)['you']);
        $this->assertSame([['word' => 'please', 'confidence' => 0.98, 'start_ms' => 2000]], $outcome->extras);
        $this->assertLessThan(100.0, $outcome->flowScore);
    }

    public function test_british_spellings_homophones_contractions_and_compounds_count_as_the_same_word()
    {
        $cases = [
            ['What colour is the centre?', 'what color is the center'],
            ['Your suite is ready.', 'your sweet is ready'],
            ['I am at the desk.', "i'm at the desk"],
            ["I'm at the desk.", 'i am at the desk'],
            ['Early check-in is free.', 'early checkin is free'],
            ['We apologise for the wait.', 'we apologize for the weight'],
        ];

        foreach ($cases as [$text, $spoken]) {
            $outcome = $this->scorer->score(ReferenceText::from($text), $this->heard($spoken));

            $this->assertTrue($outcome->isPerfect(), sprintf('"%s" heard as "%s": %s', $text, $spoken, json_encode($this->statuses($outcome))));
            $this->assertSame([], $outcome->extras, $text);
        }
    }

    public function test_numbers_are_shown_but_never_judged()
    {
        $outcome = $this->scorer->score(
            ReferenceText::from('Room 214 is ready.'),
            $this->heard('room two fourteen is ready'),
        );

        $this->assertSame('skipped', $this->statuses($outcome)['214']);
        $this->assertSame([], $outcome->extras);
        $this->assertTrue($outcome->isPerfect());
    }

    public function test_silence_is_not_heard_and_scores_zero()
    {
        $outcome = $this->scorer->score(
            ReferenceText::from('How can I help you?'),
            $this->heard('um'),
        );

        $this->assertFalse($outcome->heardAnything);
        $this->assertSame('not_heard', $outcome->level);
        $this->assertSame(0.0, $outcome->score);
        $this->assertSame(1, $outcome->fillers);
        $this->assertSame(['missed'], array_values(array_unique(array_column($outcome->words, 'status'))));
        $this->assertFalse($outcome->needsHintedListen());
    }

    public function test_a_confidence_dip_against_the_calibrated_reference_is_unclear()
    {
        $reference = ReferenceText::from('for three nights');
        $knowledge = new PronunciationKnowledge(calibration: [
            0 => ['key' => 'for', 'confidence' => 1.0, 'heard' => true],
            1 => ['key' => 'three', 'confidence' => 1.0, 'heard' => true],
            2 => ['key' => 'nights', 'confidence' => 0.99, 'heard' => true],
        ]);

        $outcome = $this->scorer->score($reference, $this->heard('for three:0.82 nights'), knowledge: $knowledge);

        $this->assertSame(['for' => 'correct', 'three' => 'unclear', 'nights' => 'correct'], $this->statuses($outcome));
    }

    public function test_a_word_the_reference_audio_itself_fails_on_is_not_judged()
    {
        $knowledge = new PronunciationKnowledge(calibration: [
            0 => ['key' => 'do', 'confidence' => null, 'heard' => false],
        ]);

        $outcome = $this->scorer->score(
            ReferenceText::from('Do you need parking?'),
            $this->heard('you need parking'),
            knowledge: $knowledge,
        );

        $this->assertSame('skipped', $this->statuses($outcome)['Do']);
        $this->assertTrue($outcome->isPerfect());
    }

    public function test_the_first_word_of_a_weak_reference_is_judged_leniently()
    {
        // The probe's reference audio earned 0.46 on its first word: a
        // learner at 0.4 on it is not unclear.
        $knowledge = new PronunciationKnowledge(calibration: [
            0 => ['key' => 'do', 'confidence' => 0.46, 'heard' => true],
        ]);

        $outcome = $this->scorer->score(
            ReferenceText::from('Do you need parking?'),
            $this->heard('do:0.4 you need parking'),
            knowledge: $knowledge,
        );

        $this->assertSame('correct', $this->statuses($outcome)['Do']);
    }

    public function test_hesitations_long_pauses_and_slow_speech_lower_flow()
    {
        $reference = ReferenceText::from('How can I help you today?');

        $smooth = $this->scorer->score($reference, $this->heard('how can i help you today'));
        $halting = $this->scorer->score($reference, $this->heard('how um can uh i help you today', gapMs: 900));

        $this->assertSame(100.0, $smooth->flowScore);
        $this->assertSame(2, $halting->fillers);
        $this->assertGreaterThan(0, $halting->longPauses);
        $this->assertLessThan(70.0, $halting->flowScore);
        $this->assertTrue($halting->isPerfect(), 'hesitating is a flow problem, not a wrong word');
    }

    public function test_without_confidences_the_clarity_score_is_left_out()
    {
        $outcome = $this->scorer->score(
            ReferenceText::from('Good morning'),
            $this->heard('good morning', withConfidence: false),
        );

        $this->assertNull($outcome->clarityScore);
        $this->assertSame(100.0, $outcome->score);
    }

    public function test_an_unrelated_word_is_different_not_mispronounced()
    {
        $outcome = $this->scorer->score(
            ReferenceText::from('Your room is ready'),
            $this->heard('your sim is ready'),
        );

        $this->assertSame('different', $this->statuses($outcome)['room']);
    }
}

<?php

namespace Tests\Unit;

use App\Contracts\AiUsageInfo;
use App\Enums\AudioSpeed;
use App\Enums\LexiconKind;
use App\Models\AiScenario;
use App\Services\Ai\FakeAiProvider;
use App\Services\Stt\FakeSpeechToTextProvider;
use App\Services\Tts\FakeTtsProvider;
use Illuminate\Foundation\Testing\TestCase;

/**
 * The fake providers are what the seeder, every local run and every other
 * lane's tests see (spec 0003 Part C). They must be deterministic and must
 * reproduce the mockup copy exactly (G.5, G.6).
 *
 * Extends the framework TestCase directly: no database is needed and the
 * app-level TestCase seeds roles on every setUp.
 */
class FakeProvidersTest extends TestCase
{
    // ------------------------------------------------------------ role-play

    public function test_the_check_in_script_walks_one_guest_line_per_guest_turn()
    {
        $ai = new FakeAiProvider;
        $scenario = $this->scenario('check-in');

        $this->assertSame('Hello! I have a reservation for tonight.', $ai->roleplayReply($scenario, [])->text);

        $transcript = [
            ['role' => 'guest', 'text' => 'Hello! I have a reservation for tonight.', 'at' => 't1'],
            ['role' => 'employee', 'text' => 'Good evening. May I have your name, please?', 'at' => 't2'],
        ];
        $this->assertSame("Yes, it's John Miller.", $ai->roleplayReply($scenario, $transcript)->text);

        $transcript[] = ['role' => 'guest', 'text' => "Yes, it's John Miller.", 'at' => 't3'];
        $transcript[] = ['role' => 'employee', 'text' => 'Here is your key.', 'at' => 't4'];
        $this->assertSame('Great, thank you! What time is breakfast?', $ai->roleplayReply($scenario, $transcript)->text);

        $transcript[] = ['role' => 'guest', 'text' => 'Great, thank you! What time is breakfast?', 'at' => 't5'];
        $this->assertSame('Perfect. Thank you!', $ai->roleplayReply($scenario, $transcript)->text);
    }

    public function test_a_conversation_past_the_script_gets_the_closing_line()
    {
        $ai = new FakeAiProvider;
        $transcript = array_fill(0, 4, ['role' => 'guest', 'text' => 'x', 'at' => 't']);

        $this->assertSame(FakeAiProvider::CLOSING_LINE, $ai->roleplayReply($this->scenario('check-in'), $transcript)->text);
    }

    public function test_an_unscripted_scenario_uses_the_generic_script()
    {
        $ai = new FakeAiProvider;

        $this->assertSame(
            FakeAiProvider::$scripts[FakeAiProvider::FALLBACK_SCRIPT][0],
            // All six seeded slugs are scripted (spec G.5), so an unscripted
            // one has to be invented.
            $ai->roleplayReply($this->scenario('lost-luggage'), [])->text,
        );
    }

    public function test_replies_report_zero_usage_from_the_fake_provider()
    {
        $reply = (new FakeAiProvider)->roleplayReply($this->scenario('check-in'), []);

        $this->assertEquals(AiUsageInfo::none(), $reply->usage);
        $this->assertSame('fake', $reply->usage->provider);
    }

    public function test_the_evaluation_is_exactly_the_mockup_feedback()
    {
        $evaluation = (new FakeAiProvider)->evaluateRoleplay($this->scenario('check-in'), []);

        $this->assertSame(
            ['pronunciation' => 70, 'grammar' => 60, 'vocabulary' => 80, 'fluency' => 70, 'politeness' => 80],
            $evaluation->criteriaScores(),
        );
        $this->assertSame(70, $evaluation->overall);

        $this->assertSame([
            'summary_label' => 'Good try!',
            'summary_text' => 'Keep practicing. You can try again.',
            'did_well' => [
                'You greeted the guest politely.',
                "You asked for the guest's name correctly.",
                'You gave the breakfast time clearly.',
                'You used a friendly and professional tone.',
            ],
            'improve' => [
                ['title' => 'Room information', 'text' => 'The sentence was not clear. Try to use a more natural expression.'],
            ],
            'better_expression' => [
                'yours' => 'You are in room fifth floor.',
                'better' => 'Your room is on the fifth floor.',
            ],
            'key_phrase' => '“Your room is on the fifth floor.”',
            'footnote' => 'Use clear and complete sentences. Guests may not understand short or incomplete phrases.',
        ], $evaluation->toFeedbackArray());

        // Every criterion carries a comment, so the export is never a bare number (AIE-04).
        foreach ($evaluation->criteria as $criterion) {
            $this->assertNotSame('', $criterion['comment']);
        }
    }

    // -------------------------------------------------------------- lexicon

    public function test_a_lexicon_draft_is_templated_around_the_word()
    {
        $draft = (new FakeAiProvider)->generateLexicon('Towel', LexiconKind::Word, 'Hotel department: Housekeeping');

        $this->assertStringContainsString('Towel', $draft->arabicMeaning);
        $this->assertStringContainsString('Towel', $draft->simpleExplanation);
        $this->assertStringContainsString('towel', $draft->hotelExample);
        $this->assertStringContainsString('Housekeeping', $draft->hotelExample);
        $this->assertStringContainsString('Towel', $draft->hotelExampleArabic);
        $this->assertSame('n', $draft->partOfSpeech);
        $this->assertNull($draft->ipa);

        $this->assertSame(
            ['arabic_meaning', 'simple_explanation', 'hotel_example', 'hotel_example_arabic', 'ipa', 'part_of_speech'],
            array_keys($draft->toArray()),
        );
    }

    public function test_an_expression_draft_is_a_phrase()
    {
        $draft = (new FakeAiProvider)->generateLexicon('How can I help you?', LexiconKind::Expression, '');

        $this->assertSame('phrase', $draft->partOfSpeech);
        $this->assertStringContainsString('How can I help you?', $draft->hotelExample);
    }

    // -------------------------------------------------------------- writing

    public function test_a_writing_evaluation_has_four_criteria_and_a_better_answer()
    {
        $evaluation = (new FakeAiProvider)->evaluateWriting([
            'id' => 'i1',
            'scenario' => 'A guest emails to ask about room rates.',
            'information' => ['Double room: 12,000 DZD per night', 'Breakfast: included'],
            'min_words' => 20,
        ], 'Dear guest, the room is 12000 per night with breakfast.');

        $this->assertSame(['task_completion', 'accuracy', 'politeness', 'clarity'], array_keys($evaluation->criteria));

        foreach ($evaluation->criteria as $criterion) {
            $this->assertGreaterThanOrEqual(70, $criterion['score']);
            $this->assertLessThanOrEqual(85, $criterion['score']);
            $this->assertNotSame('', $criterion['comment']);
        }

        $this->assertSame(77.5, $evaluation->overallScore());
        $this->assertStringContainsString('12,000 DZD', $evaluation->betterAnswer);
        $this->assertSame(['criteria', 'better_answer', 'summary', 'corrections'], array_keys($evaluation->toArray()));
    }

    // ------------------------------------------------------------------ tts

    public function test_the_fake_tts_writes_a_valid_16_bit_mono_22050_hz_wav()
    {
        $audio = (new FakeTtsProvider)->synthesise('How can I help you?', 'guesvia-en', AudioSpeed::Normal);

        $this->assertSame('audio/wav', $audio->mime);
        $this->assertSame('wav', $audio->extension);
        $this->assertSame(600, $audio->durationMs);

        $this->assertSame('RIFF', substr($audio->binary, 0, 4));
        $this->assertSame('WAVE', substr($audio->binary, 8, 4));
        $this->assertSame('fmt ', substr($audio->binary, 12, 4));

        /** @var array{format: int, channels: int, rate: int, bits: int} $fmt */
        $fmt = unpack('vformat/vchannels/Vrate/Vbyterate/valign/vbits', substr($audio->binary, 20, 16));
        $this->assertSame(1, $fmt['format']);
        $this->assertSame(1, $fmt['channels']);
        $this->assertSame(22050, $fmt['rate']);
        $this->assertSame(16, $fmt['bits']);

        $this->assertSame('data', substr($audio->binary, 36, 4));
        /** @var array{size: int} $data */
        $data = unpack('Vsize', substr($audio->binary, 40, 4));
        // 0.6 s × 22050 samples × 2 bytes.
        $this->assertSame(26460, $data['size']);
        $this->assertSame(44 + 26460, strlen($audio->binary));
    }

    public function test_the_slow_file_is_longer_than_the_normal_one()
    {
        $tts = new FakeTtsProvider;

        $normal = $tts->synthesise('Towel', 'guesvia-en', AudioSpeed::Normal);
        $slow = $tts->synthesise('Towel', 'guesvia-en', AudioSpeed::Slow);

        $this->assertSame(900, $slow->durationMs);
        $this->assertSame(44 + 39690, strlen($slow->binary));
        $this->assertGreaterThan(strlen($normal->binary), strlen($slow->binary));
    }

    public function test_the_pitch_is_derived_from_the_text_and_is_deterministic()
    {
        $tts = new FakeTtsProvider;

        $this->assertSame(220 + (crc32('Towel') % 440), FakeTtsProvider::frequencyFor('Towel'));
        $this->assertGreaterThanOrEqual(220, FakeTtsProvider::frequencyFor('Pillow'));
        $this->assertLessThan(660, FakeTtsProvider::frequencyFor('Pillow'));

        $this->assertSame(
            $tts->synthesise('Towel', 'guesvia-en', AudioSpeed::Normal)->binary,
            $tts->synthesise('Towel', 'guesvia-en', AudioSpeed::Normal)->binary,
        );
        $this->assertNotSame(
            $tts->synthesise('Towel', 'guesvia-en', AudioSpeed::Normal)->binary,
            $tts->synthesise('Pillow', 'guesvia-en', AudioSpeed::Normal)->binary,
        );
    }

    // ------------------------------------------------------------------ stt

    public function test_the_fake_stt_returns_the_voice_message_marker()
    {
        $this->assertSame('[voice message]', (new FakeSpeechToTextProvider)->transcribe('/tmp/anything.webm', 'audio/webm'));
    }

    // -------------------------------------------------------------- helpers

    /**
     * An in-memory scenario: the fake provider keys its script on the slug
     * and never touches the database.
     */
    private function scenario(string $slug): AiScenario
    {
        $scenario = new AiScenario;
        $scenario->slug = $slug;

        return $scenario;
    }
}

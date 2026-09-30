<?php

namespace Tests\Unit;

use App\Enums\ActivityType;
use App\Services\Learning\ActivityScorer;
use Database\Factories\ActivityFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The client's new question types score the way the builder promises
 * (client report 2026-09-29; TEST-06): a short answer or a blank is right
 * when it equals an accepted answer, ignoring case, spaces and
 * punctuation; matching and ordering must be exact; the three media
 * questions score like any option question.
 */
class QuestionTypeScoringTest extends TestCase
{
    private ActivityScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scorer = new ActivityScorer;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(ActivityType $type): array
    {
        return ActivityFactory::payloadFor($type)['items'];
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public static function shortAnswers(): array
    {
        return [
            'exact' => ['key card', true],
            'another accepted answer' => ['room key', true],
            'capitals and spaces' => ['  Key   CARD ', true],
            'punctuation' => ['Key-card.', true],
            'as {text}' => [['text' => 'The key!'], true],
            'a different answer' => ['passport', false],
            'empty' => ['   ', false],
            'nothing' => [null, false],
        ];
    }

    #[DataProvider('shortAnswers')]
    public function test_a_short_answer_matches_any_accepted_answer(mixed $answer, bool $right)
    {
        $result = $this->scorer->scoreItems(ActivityType::ShortAnswer, $this->items(ActivityType::ShortAnswer), ['i1' => $answer]);

        $this->assertSame(['i1' => $right], $result->perItem);
        $this->assertSame($right ? 1 : 0, $result->score);
    }

    public function test_a_bare_short_answer_counts_for_a_one_item_question()
    {
        $result = $this->scorer->scoreItems(ActivityType::ShortAnswer, $this->items(ActivityType::ShortAnswer), ['Room Key']);

        $this->assertTrue($result->isCorrect);
    }

    public function test_every_blank_must_hold_one_of_its_accepted_words()
    {
        $items = $this->items(ActivityType::FillBlank);

        $right = $this->scorer->scoreItems(ActivityType::FillBlank, $items, ['i1' => ['b1' => 'id', 'b2' => ' Room key ']]);
        $oneWrong = $this->scorer->scoreItems(ActivityType::FillBlank, $items, ['i1' => ['b1' => 'passport', 'b2' => 'towel']]);
        $oneMissing = $this->scorer->scoreItems(ActivityType::FillBlank, $items, ['i1' => ['b1' => 'passport']]);

        $this->assertTrue($right->isCorrect);
        $this->assertFalse($oneWrong->isCorrect);
        $this->assertFalse($oneMissing->isCorrect);
        $this->assertSame(1, $right->maxScore);
    }

    public function test_matching_needs_every_pair()
    {
        $items = $this->items(ActivityType::Matching);

        $this->assertTrue($this->scorer->scoreItems(ActivityType::Matching, $items, ['i1' => ['1' => 'a', '2' => 'b', '3' => 'c']])->isCorrect);
        $this->assertFalse($this->scorer->scoreItems(ActivityType::Matching, $items, ['i1' => ['1' => 'b', '2' => 'a', '3' => 'c']])->isCorrect);
        $this->assertFalse($this->scorer->scoreItems(ActivityType::Matching, $items, ['i1' => ['1' => 'a']])->isCorrect);
    }

    public function test_ordering_needs_the_exact_order()
    {
        $items = $this->items(ActivityType::Ordering);

        $this->assertTrue($this->scorer->scoreItems(ActivityType::Ordering, $items, ['i1' => ['s1', 's2', 's3']])->isCorrect);
        $this->assertFalse($this->scorer->scoreItems(ActivityType::Ordering, $items, ['i1' => ['s2', 's1', 's3']])->isCorrect);
        // The order the learner is shown is never the answer.
        $shown = array_column($items[0]['sentences'], 'id');
        $this->assertFalse($this->scorer->scoreItems(ActivityType::Ordering, $items, ['i1' => $shown])->isCorrect);
    }

    /**
     * @return array<string, array{0: ActivityType}>
     */
    public static function mediaQuestions(): array
    {
        return [
            'audio' => [ActivityType::AudioQuestion],
            'image' => [ActivityType::ImageQuestion],
            'video' => [ActivityType::VideoQuestion],
        ];
    }

    #[DataProvider('mediaQuestions')]
    public function test_a_media_question_scores_its_chosen_option(ActivityType $type)
    {
        $items = $this->items($type);

        $this->assertTrue($this->scorer->scoreItems($type, $items, ['i1' => 'a'])->isCorrect);
        $this->assertFalse($this->scorer->scoreItems($type, $items, ['i1' => 'b'])->isCorrect);
    }

    public function test_the_typed_answer_normaliser()
    {
        $this->assertSame('don t', ActivityScorer::normaliseTyped('Don’t!'));
        $this->assertSame('good morning', ActivityScorer::normaliseTyped("  Good\tmorning... "));
    }
}

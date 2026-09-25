<?php

namespace Tests\Unit;

use App\Enums\ActivityType;
use App\Services\Learning\ActivityScorer;
use App\Services\Learning\ScoreResult;
use Database\Factories\ActivityFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every activity type scores the way spec 0003 B.9 says, with no database:
 * the scorer only needs the type and the frozen items (TEST-06, DATA-11).
 *
 * max_score is the number of items, score the number right, is_correct
 * whether all were right. Speaking and writing are never auto scored.
 */
class ActivityScorerTest extends TestCase
{
    private ActivityScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scorer = new ActivityScorer;
    }

    // -------------------------------------------------------- option types

    /**
     * @return array<string, array{0: ActivityType}>
     */
    public static function optionTypes(): array
    {
        return [
            'listen_choose' => [ActivityType::ListenChoose],
            'look_listen' => [ActivityType::LookListen],
            'best_response' => [ActivityType::BestResponse],
            'watch_respond' => [ActivityType::WatchRespond],
            'words_sentences' => [ActivityType::WordsSentences],
            'multiple_choice' => [ActivityType::MultipleChoice],
        ];
    }

    #[DataProvider('optionTypes')]
    public function test_an_option_type_is_right_when_the_chosen_id_matches(ActivityType $type)
    {
        $items = $this->itemsFor($type);
        $correct = $items[0]['correct'];

        $right = $this->scorer->scoreItems($type, $items, ['i1' => $correct]);
        $wrong = $this->scorer->scoreItems($type, $items, ['i1' => 'zzz']);

        $this->assertFullMarks($right);
        $this->assertSame(['i1' => true], $right->perItem);

        $this->assertSame(0, $wrong->score);
        $this->assertSame(1, $wrong->maxScore);
        $this->assertFalse($wrong->isCorrect);
        $this->assertSame(['i1' => false], $wrong->perItem);
    }

    public function test_an_option_answer_is_compared_as_a_trimmed_string()
    {
        $items = $this->itemsFor(ActivityType::MultipleChoice); // correct "A"

        $this->assertFullMarks($this->scorer->scoreItems(ActivityType::MultipleChoice, $items, ['i1' => ' A ']));
        $this->assertNoMarks($this->scorer->scoreItems(ActivityType::MultipleChoice, $items, ['i1' => 'a']));
        $this->assertNoMarks($this->scorer->scoreItems(ActivityType::MultipleChoice, $items, ['i1' => '']));
        $this->assertNoMarks($this->scorer->scoreItems(ActivityType::MultipleChoice, $items, ['i1' => ['A']]));
    }

    public function test_a_single_item_question_may_post_its_answer_bare()
    {
        $items = $this->itemsFor(ActivityType::MultipleChoice);

        $this->assertFullMarks($this->scorer->scoreItems(ActivityType::MultipleChoice, $items, ['A']));
    }

    // ----------------------------------------------------------- pair maps

    public function test_listen_match_is_right_only_when_every_pair_matches()
    {
        $items = $this->itemsFor(ActivityType::ListenMatch); // 1->a, 2->b, 3->c

        $this->assertFullMarks($this->scorer->scoreItems(ActivityType::ListenMatch, $items, [
            'i1' => ['1' => 'a', '2' => 'b', '3' => 'c'],
        ]));

        // One pair wrong is a wrong item, not a fraction of one.
        $this->assertNoMarks($this->scorer->scoreItems(ActivityType::ListenMatch, $items, [
            'i1' => ['1' => 'a', '2' => 'c', '3' => 'b'],
        ]));

        // A missing pair is wrong.
        $this->assertNoMarks($this->scorer->scoreItems(ActivityType::ListenMatch, $items, [
            'i1' => ['1' => 'a', '2' => 'b'],
        ]));

        // Extra pairs beyond the expected ones do not hurt.
        $this->assertFullMarks($this->scorer->scoreItems(ActivityType::ListenMatch, $items, [
            'i1' => ['1' => 'a', '2' => 'b', '3' => 'c', '9' => 'd'],
        ]));
    }

    public function test_a_pair_map_accepts_integer_keys_from_json_decoding()
    {
        $items = $this->itemsFor(ActivityType::ListenMatch);

        // json_decode turns {"1": "a"} into [1 => 'a'].
        $this->assertFullMarks($this->scorer->scoreItems(ActivityType::ListenMatch, $items, [
            'i1' => [1 => 'a', 2 => 'b', 3 => 'c'],
        ]));
    }

    // ------------------------------------------------------- ordered lists

    /**
     * @return array<string, array{0: ActivityType}>
     */
    public static function orderTypes(): array
    {
        return [
            'dialogue_order' => [ActivityType::DialogueOrder],
            'picture_order' => [ActivityType::PictureOrder],
        ];
    }

    #[DataProvider('orderTypes')]
    public function test_an_order_type_needs_the_whole_sequence_in_place(ActivityType $type)
    {
        $items = $this->itemsFor($type);
        $order = $items[0]['order'];

        $this->assertFullMarks($this->scorer->scoreItems($type, $items, ['i1' => $order]));

        $swapped = $order;
        [$swapped[0], $swapped[1]] = [$swapped[1], $swapped[0]];
        $this->assertNoMarks($this->scorer->scoreItems($type, $items, ['i1' => $swapped]));

        // A shorter list is wrong even if its prefix is right.
        $this->assertNoMarks($this->scorer->scoreItems($type, $items, ['i1' => array_slice($order, 0, -1)]));

        // A non-list answer is wrong, not an exception.
        $this->assertNoMarks($this->scorer->scoreItems($type, $items, ['i1' => 'A']));
    }

    // ------------------------------------------------------ not auto scored

    /**
     * @return array<string, array{0: ActivityType, 1: array<string, mixed>}>
     */
    public static function ungradedTypes(): array
    {
        return [
            'speaking' => [ActivityType::Speaking, ['i1' => ['recording_media_id' => 44, 'duration_ms' => 5200]]],
            'writing' => [ActivityType::Writing, ['i1' => ['text' => 'Dear guest, the double room is 12,000 DZD per night.']]],
        ];
    }

    /**
     * @param  array<string, mixed>  $answer
     */
    #[DataProvider('ungradedTypes')]
    public function test_speaking_and_writing_store_the_answer_without_a_verdict(ActivityType $type, array $answer)
    {
        $result = $this->scorer->scoreItems($type, $this->itemsFor($type), $answer);

        $this->assertNull($result->score);
        $this->assertNull($result->isCorrect);
        $this->assertSame(1, $result->maxScore);
        $this->assertSame(['i1' => null], $result->perItem);
        $this->assertFalse($result->isAutoScored());
        $this->assertNull($result->percent());
        $this->assertFalse($type->isAutoScored());
    }

    // ------------------------------------------------------ partial credit

    public function test_partial_credit_counts_correct_items_out_of_the_total()
    {
        $items = [
            ['id' => 'i1', 'options' => [], 'correct' => 'a'],
            ['id' => 'i2', 'options' => [], 'correct' => 'b'],
            ['id' => 'i3', 'options' => [], 'correct' => 'c'],
            ['id' => 'i4', 'options' => [], 'correct' => 'd'],
            ['id' => 'i5', 'options' => [], 'correct' => 'e'],
        ];

        $result = $this->scorer->scoreItems(ActivityType::ListenChoose, $items, [
            'i1' => 'a',
            'i2' => 'x',
            'i3' => 'c',
            // i4 never answered
            'i5' => 'e',
        ]);

        $this->assertSame(3, $result->score);
        $this->assertSame(5, $result->maxScore);
        $this->assertFalse($result->isCorrect);
        $this->assertSame(60.0, $result->percent());
        $this->assertSame([
            'i1' => true,
            'i2' => false,
            'i3' => true,
            'i4' => false,
            'i5' => true,
        ], $result->perItem);
        $this->assertSame(['score' => 3, 'max_score' => 5, 'is_correct' => false], $result->toAttemptColumns());
    }

    public function test_partial_credit_mixes_shapes_only_within_one_type_but_counts_every_item()
    {
        $items = [
            ['id' => 'i1', 'order' => ['s2', 's1']],
            ['id' => 'i2', 'order' => ['s3', 's4']],
        ];

        $result = $this->scorer->scoreItems(ActivityType::DialogueOrder, $items, [
            'i1' => ['s2', 's1'],
            'i2' => ['s4', 's3'],
        ]);

        $this->assertSame(1, $result->score);
        $this->assertSame(2, $result->maxScore);
        $this->assertFalse($result->isCorrect);
    }

    public function test_an_empty_answer_scores_zero_and_never_throws()
    {
        foreach (ActivityType::cases() as $type) {
            $result = $this->scorer->scoreItems($type, $this->itemsFor($type), []);

            $this->assertSame(1, $result->maxScore, $type->value);
            $this->assertSame($type->isAutoScored() ? 0 : null, $result->score, $type->value);
            $this->assertSame($type->isAutoScored() ? false : null, $result->isCorrect, $type->value);
        }
    }

    public function test_a_version_with_no_items_is_worth_nothing_and_is_not_correct()
    {
        $result = $this->scorer->scoreItems(ActivityType::MultipleChoice, [], []);

        $this->assertSame(0, $result->score);
        $this->assertSame(0, $result->maxScore);
        $this->assertFalse($result->isCorrect);
        $this->assertNull($result->percent());
    }

    // -------------------------------------------------------------- helpers

    /**
     * @return list<array<string, mixed>>
     */
    private function itemsFor(ActivityType $type): array
    {
        /** @var list<array<string, mixed>> $items */
        $items = ActivityFactory::payloadFor($type)['items'];

        return $items;
    }

    private function assertFullMarks(ScoreResult $result): void
    {
        $this->assertSame($result->maxScore, $result->score);
        $this->assertTrue($result->isCorrect);
        $this->assertTrue($result->isAutoScored());
    }

    private function assertNoMarks(ScoreResult $result): void
    {
        $this->assertSame(0, $result->score);
        $this->assertFalse($result->isCorrect);
    }
}

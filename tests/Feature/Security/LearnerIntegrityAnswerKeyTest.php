<?php

namespace Tests\Feature\Security;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Attempt;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * Security audit pass 2 (2026-11-08): on a Pre/Post-test page the ids of
 * ordering lines and matching prompts/targets are opaque per-sitting tokens
 * in a per-sitting order, so they no longer spell the answer (`s1, s2, s3`,
 * prompt 1 → target a). The answer is stored with the real ids so the
 * research data stays comparable (TEST-03, TEST-06, DATA-01).
 */
class LearnerIntegrityAnswerKeyTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    /**
     * @param  list<ActivityType>  $types
     */
    private function test(array $types): Test
    {
        $test = Test::factory()->pre()->untimed()->create(['department_id' => $this->department->id]);

        foreach ($types as $index => $type) {
            $activity = Activity::factory()->ofType($type)->create();
            $test->questions()->create(['activity_id' => $activity->id, 'position' => $index + 1]);
        }

        return $test;
    }

    private function start(User $learner, Test $test): TestAttempt
    {
        $this->actingAs($learner)->post(route('learn.tests.start', $test))->assertRedirect();

        return TestAttempt::query()->where('user_id', $learner->id)->latest('id')->firstOrFail();
    }

    private function page(User $learner, Test $test, TestAttempt $attempt, int $number = 1): TestResponse
    {
        return $this->actingAs($learner)
            ->get(route('learn.tests.question', ['test' => $test, 'attempt' => $attempt, 'number' => $number]))
            ->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function item(TestResponse $response): array
    {
        /** @var array<string, mixed> $item */
        $item = $response->viewData('page')['props']['activity']['items'][0];

        return $item;
    }

    /**
     * @param  array<array-key, mixed>  $entries
     * @return array<string, string> text => id
     */
    private static function idsByText(array $entries, string $textKey): array
    {
        $map = [];

        foreach ($entries as $entry) {
            $row = is_array($entry) ? $entry : [];
            $map[(string) $row[$textKey]] = (string) $row['id'];
        }

        return $map;
    }

    public function test_ordering_line_ids_are_opaque_and_a_token_answer_scores_with_the_real_ids()
    {
        $learner = $this->learner();
        $test = $this->test([ActivityType::Ordering]);
        $attempt = $this->start($learner, $test);

        $item = $this->item($this->page($learner, $test, $attempt));
        $this->assertIsArray($item['sentences']);
        $this->assertCount(3, $item['sentences']);

        $ids = array_column($item['sentences'], 'id');

        foreach (['s1', 's2', 's3'] as $real) {
            $this->assertNotContains($real, $ids);
        }

        $json = json_encode($this->item($this->page($learner, $test, $attempt)));
        $this->assertIsString($json);
        $this->assertDoesNotMatchRegularExpression('/"s[123]"/', $json);

        $byText = self::idsByText($item['sentences'], 'text');
        $tokens = [$byText['Greet the guest.'], $byText['Ask for the booking name.'], $byText['Give the key card.']];

        $this->actingAs($learner)->post(route('learn.tests.finish', ['test' => $test, 'attempt' => $attempt]), [
            'number' => 1, 'answer' => ['i1' => $tokens],
        ])->assertRedirect();

        $row = Attempt::query()->where('test_attempt_id', $attempt->id)->firstOrFail();
        $this->assertSame(['i1' => ['s1', 's2', 's3']], $row->raw_answer);
        $this->assertTrue($row->is_correct);
    }

    public function test_guessing_the_real_ordering_ids_scores_nothing()
    {
        $learner = $this->learner();
        $test = $this->test([ActivityType::Ordering]);
        $attempt = $this->start($learner, $test);

        $this->actingAs($learner)->post(route('learn.tests.finish', ['test' => $test, 'attempt' => $attempt]), [
            'number' => 1, 'answer' => ['i1' => ['s1', 's2', 's3']],
        ])->assertRedirect();

        $row = Attempt::query()->where('test_attempt_id', $attempt->id)->firstOrFail();
        $this->assertFalse($row->is_correct);
    }

    public function test_matching_ids_are_opaque_and_a_token_answer_scores_with_the_real_ids()
    {
        $learner = $this->learner();
        $test = $this->test([ActivityType::Matching]);
        $attempt = $this->start($learner, $test);

        $item = $this->item($this->page($learner, $test, $attempt));
        $this->assertIsArray($item['prompts']);
        $this->assertIsArray($item['targets']);

        $promptIds = array_column($item['prompts'], 'id');
        $targetIds = array_column($item['targets'], 'id');

        foreach (['1', '2', '3'] as $real) {
            $this->assertNotContains($real, $promptIds);
        }

        foreach (['a', 'b', 'c'] as $real) {
            $this->assertNotContains($real, $targetIds);
        }

        $prompts = self::idsByText($item['prompts'], 'text');
        $targets = self::idsByText($item['targets'], 'text');
        $answer = [];

        foreach (['Towel', 'Pillow', 'Room key'] as $word) {
            $answer[$prompts[$word]] = $targets[$word];
        }

        // A revisited question restores the learner's answer in tokens.
        $this->actingAs($learner)->put(route('learn.tests.answer', ['test' => $test, 'attempt' => $attempt, 'number' => 1]), [
            'answer' => ['i1' => $answer], 'to' => 1,
        ])->assertRedirect();

        $saved = $this->page($learner, $test, $attempt)->viewData('page')['props']['savedAnswer'];
        $this->assertSame(['i1' => $answer], $saved);

        $this->actingAs($learner)->post(route('learn.tests.finish', ['test' => $test, 'attempt' => $attempt]), [
            'number' => 1, 'answer' => ['i1' => $answer],
        ])->assertRedirect();

        $row = Attempt::query()->where('test_attempt_id', $attempt->id)->firstOrFail();
        $this->assertSame(['i1' => ['1' => 'a', '2' => 'b', '3' => 'c']], $row->raw_answer);
        $this->assertTrue($row->is_correct);
    }

    public function test_the_tokens_differ_between_sittings()
    {
        $test = $this->test([ActivityType::Ordering]);
        $first = $this->learner();
        $second = $this->learner(['username' => 'amine']);

        $a = array_column($this->item($this->page($first, $test, $this->start($first, $test)))['sentences'], 'id');
        $b = array_column($this->item($this->page($second, $test, $this->start($second, $test)))['sentences'], 'id');

        $this->assertSame([], array_intersect($a, $b));
    }
}

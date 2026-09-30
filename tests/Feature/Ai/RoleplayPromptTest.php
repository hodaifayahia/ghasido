<?php

namespace Tests\Feature\Ai;

use App\Enums\EnglishLevel;
use App\Models\AiScenario;
use App\Services\Ai\RoleplayPrompt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shared role-play prompt (RP-04, RP-08, AIE-04; spec 0005 §2).
 *
 * One builder feeds every provider and the voice call, so the guest speaks
 * at the learner's measured level and paces the conversation to the
 * scenario's bound.
 */
class RoleplayPromptTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $roles
     * @return list<array{role: string, text: string}>
     */
    private static function transcript(array $roles): array
    {
        return array_map(static fn (string $role): array => ['role' => $role, 'text' => 'line'], $roles);
    }

    private function scenario(int $min = 2, int $max = 4): AiScenario
    {
        return AiScenario::factory()->create(['min_turns' => $min, 'max_turns' => $max]);
    }

    public function test_the_three_levels_move_up_one_step_and_stop_at_advanced()
    {
        $this->assertSame(EnglishLevel::Intermediate, EnglishLevel::Beginner->next());
        $this->assertSame(EnglishLevel::Advanced, EnglishLevel::Intermediate->next());
        $this->assertNull(EnglishLevel::Advanced->next());
        $this->assertSame(['beginner', 'intermediate', 'advanced'], array_column(EnglishLevel::options(), 'value'));
    }

    public function test_the_guest_is_pitched_at_the_learners_level_or_a_low_one_by_default()
    {
        $scenario = $this->scenario();

        $this->assertStringContainsString(
            EnglishLevel::Intermediate->promptDescription(),
            RoleplayPrompt::replySystem($scenario, [], EnglishLevel::Intermediate),
        );
        $this->assertStringContainsString('low English level', RoleplayPrompt::replySystem($scenario, [], null));
        $this->assertStringContainsString(EnglishLevel::Beginner->promptDescription(), RoleplayPrompt::forVoice($scenario, '', EnglishLevel::Beginner));
    }

    public function test_the_pacing_line_follows_the_conversation_to_its_bound()
    {
        $scenario = $this->scenario(min: 2, max: 4);

        $this->assertStringContainsString('opening line', RoleplayPrompt::pacingLine($scenario, []));
        $this->assertStringContainsString('too early to finish', RoleplayPrompt::pacingLine($scenario, self::transcript(['guest', 'employee'])));
        $this->assertStringContainsString('may close', RoleplayPrompt::pacingLine($scenario, self::transcript(['guest', 'employee', 'guest', 'employee'])));
        $this->assertStringContainsString('Begin wrapping up', RoleplayPrompt::pacingLine($scenario, self::transcript(['guest', 'employee', 'guest', 'employee', 'guest', 'employee'])));
        $this->assertStringContainsString('final line', RoleplayPrompt::pacingLine($scenario, self::transcript(['guest', 'employee', 'guest', 'employee', 'guest', 'employee', 'guest', 'employee'])));
    }

    public function test_the_cacheable_part_of_the_reply_prompt_is_the_same_every_turn()
    {
        // A prompt cache needs a byte-identical prefix (spec 0005 §5.3): the
        // pacing line is the only thing that moves, and it sits outside.
        $scenario = $this->scenario();
        $opening = RoleplayPrompt::replySystemParts($scenario, [], EnglishLevel::Intermediate);
        $later = RoleplayPrompt::replySystemParts($scenario, self::transcript(['guest', 'employee', 'guest', 'employee']), EnglishLevel::Intermediate);

        $this->assertSame($opening['stable'], $later['stable']);
        $this->assertNotSame($opening['turn'], $later['turn']);
        $this->assertStringContainsString(RoleplayPrompt::GUARD, $opening['stable']);
        $this->assertStringContainsString('"reply"', $opening['stable']);
        $this->assertStringNotContainsString('The employee has replied', $later['stable']);
        $this->assertStringContainsString('The employee has replied 2 of', $later['turn']);
        $this->assertSame(
            $opening['stable']."\n\n".$opening['turn'],
            RoleplayPrompt::replySystem($scenario, [], EnglishLevel::Intermediate),
        );
    }

    public function test_the_evaluation_prompt_carries_the_anchors_the_criteria_and_the_level()
    {
        $scenario = $this->scenario();
        $system = RoleplayPrompt::evaluationSystem($scenario, ['politeness', 'fluency'], EnglishLevel::Intermediate);

        $this->assertStringContainsString(RoleplayPrompt::SCORING_ANCHORS, $system);
        $this->assertStringContainsString('"politeness": {"score"', $system);
        $this->assertStringContainsString('"fluency": {"score"', $system);
        $this->assertStringContainsString('Intermediate level', $system);
    }
}

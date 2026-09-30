<?php

namespace Tests\Feature\Ai;

use App\Enums\Accent;
use App\Enums\EnglishLevel;
use App\Models\AiScenario;
use App\Services\Ai\OpenAiCompatibleAiProvider;
use App\Services\Ai\RoleplayPrompt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Chat Completions provider (Qwen, OpenAI, …) sends the shared role-play
 * prompt and judges answers at a low temperature (API-04, AIE-04; spec 0005
 * §2.3, §2.4).
 */
class OpenAiCompatibleAiProviderTest extends TestCase
{
    use RefreshDatabase;

    private function provider(): OpenAiCompatibleAiProvider
    {
        return new OpenAiCompatibleAiProvider(
            apiKey: 'k',
            model: 'heavy-model',
            baseUrl: 'https://llm.example.test/v1',
            fastModel: 'fast-model',
        );
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private static function reply(array $json): array
    {
        return [
            'model' => 'm',
            'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($json)]]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ];
    }

    public function test_a_role_play_reply_uses_the_fast_model_and_the_shared_prompt()
    {
        Http::fake(['*' => Http::response(self::reply(['reply' => 'Good evening!']))]);
        $scenario = AiScenario::factory()->create();

        $reply = $this->provider()->roleplayReply($scenario, [], EnglishLevel::Intermediate);

        $this->assertSame('Good evening!', $reply->text);
        Http::assertSent(function (Request $request) use ($scenario): bool {
            $system = $request['messages'][0]['content'];

            $this->assertSame('fast-model', $request['model']);
            $this->assertSame(RoleplayPrompt::replySystem($scenario, [], EnglishLevel::Intermediate), $system);
            $this->assertEqualsWithDelta(0.6, $request['temperature'], 0.001);

            return true;
        });
    }

    public function test_every_evaluation_runs_cool_on_the_main_model()
    {
        Http::fake(['*' => Http::response(self::reply(['overall' => 70, 'summary' => 'ok']))]);
        $scenario = AiScenario::factory()->create();

        $this->provider()->evaluateRoleplay($scenario, [['role' => 'employee', 'text' => 'Hello']]);
        $this->provider()->evaluateWriting(['id' => 'i1'], 'Dear guest');
        $this->provider()->evaluateSpeaking(['id' => 'i1'], 'Welcome to the hotel');

        Http::assertSentCount(3);
        Http::assertSent(function (Request $request): bool {
            $this->assertSame('heavy-model', $request['model']);
            $this->assertEqualsWithDelta(0.1, $request['temperature'], 0.001);
            $this->assertStringContainsString(RoleplayPrompt::SCORING_ANCHORS, $request['messages'][0]['content']);

            return true;
        });
    }

    public function test_a_pronunciation_guide_is_drafted_on_the_main_model_and_parsed_defensively()
    {
        Http::fake(['*' => Http::response(self::reply([
            'ipa' => '/ə ˈvɛri ˈkwaɪət ruːm/',
            'words' => [
                ['word' => 'very', 'ipa' => '/ˈvɛri/', 'syllables' => 'VE·ry', 'sounds_like' => 'VAIR-ee', 'tip' => 'Top teeth on the lip.', 'traps' => [
                    ['heard_as' => 'ferry', 'sound' => 'v → f', 'tip' => 'Make v buzz.'],
                    ['heard_as' => 'very', 'sound' => 'none', 'tip' => 'the word itself is never a trap'],
                ], 'homophones' => []],
                ['word' => '', 'ipa' => '/x/'],
                'not an object',
            ],
            'tips' => ['Link the words.', 'Stress VE-ry.', 'a third tip is dropped'],
        ]))]);

        $draft = $this->provider()->pronunciationGuide('a very quiet room', Accent::British);

        $this->assertCount(1, $draft->words);
        $this->assertSame('very', $draft->words[0]['word']);
        $this->assertSame([['heard_as' => 'ferry', 'sound' => 'v → f', 'tip' => 'Make v buzz.']], $draft->words[0]['traps']);
        $this->assertSame(['Link the words.', 'Stress VE-ry.'], $draft->tips);

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('heavy-model', $request['model']);
            $this->assertStringContainsString('British English', $request['messages'][0]['content']);
            $this->assertStringContainsString('Arabic has no /p/', $request['messages'][0]['content']);

            return true;
        });
    }

    public function test_pronunciation_coaching_uses_the_fast_model_and_keeps_only_tips_for_words_of_the_sentence()
    {
        Http::fake(['*' => Http::response(self::reply([
            'headline' => 'Nice rhythm! Now work on very.',
            'tips' => [
                ['word' => 'Very', 'tip' => 'Put your top teeth on your lower lip.'],
                ['word' => 'banana', 'tip' => 'Not a word of this sentence.'],
            ],
            'next' => 'Say very three times.',
            'arabic' => 'null',
        ]))]);

        $coaching = $this->provider()->coachPronunciation(['words' => []], ['a', 'very', 'quiet', 'room'], Accent::American, EnglishLevel::Intermediate);

        $this->assertSame('Nice rhythm! Now work on very.', $coaching->headline);
        $this->assertSame([['word' => 'Very', 'tip' => 'Put your top teeth on your lower lip.']], $coaching->tips);
        $this->assertNull($coaching->arabic);

        Http::assertSent(fn (Request $request): bool => $request['model'] === 'fast-model');
    }
}

<?php

namespace Tests\Feature\Ai;

use App\Enums\LexiconKind;
use App\Models\AiScenario;
use App\Services\Ai\AnthropicAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * The Messages API transport, with the network faked (API-02, API-04,
 * SEC-03; spec 0003 Part C): request shape, config-driven model and
 * endpoint, defensive JSON parsing, and refusal handling.
 */
class AnthropicAiProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_request_carries_the_configured_model_key_and_endpoint()
    {
        Http::fake([
            'https://ai.example.test/v1/messages' => Http::response($this->reply([
                'arabic_meaning' => 'منشفة',
                'simple_explanation' => 'A cloth you dry yourself with.',
                'hotel_example' => 'Could I have an extra towel, please?',
                'hotel_example_arabic' => 'هل يمكنني الحصول على منشفة إضافية؟',
                'ipa' => '/ˈtaʊəl/',
                'part_of_speech' => 'n',
            ], 321, 54)),
        ]);

        $provider = new AnthropicAiProvider(apiKey: 'sk-test', model: 'configured-model', baseUrl: 'https://ai.example.test');

        $draft = $provider->generateLexicon('Towel', LexiconKind::Word, 'Hotel department: Housekeeping');

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('https://ai.example.test/v1/messages', $request->url());
            $this->assertSame('sk-test', $request->header('x-api-key')[0]);
            $this->assertSame('2023-06-01', $request->header('anthropic-version')[0]);
            $this->assertSame('configured-model', $request['model']);
            $this->assertIsInt($request['max_tokens']);
            $this->assertIsString($request['system']);
            $this->assertSame('user', $request['messages'][0]['role']);
            $this->assertStringContainsString('Towel', $request['messages'][0]['content']);
            $this->assertStringContainsString('Housekeeping', $request['messages'][0]['content']);

            return true;
        });

        $this->assertSame('منشفة', $draft->arabicMeaning);
        $this->assertSame('/ˈtaʊəl/', $draft->ipa);
        $this->assertSame('n', $draft->partOfSpeech);
        $this->assertSame(321, $draft->usage->promptTokens);
        $this->assertSame(54, $draft->usage->completionTokens);
        $this->assertSame('anthropic', $draft->usage->provider);
        $this->assertSame('configured-model', $draft->usage->model);
    }

    public function test_the_default_endpoint_is_the_anthropic_api()
    {
        Http::fake(['https://api.anthropic.com/v1/messages' => Http::response($this->reply(['summary' => 'ok']))]);

        (new AnthropicAiProvider(apiKey: 'sk-test', model: 'm'))->evaluateWriting(['id' => 'i1'], 'Dear guest');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.anthropic.com/v1/messages');
    }

    public function test_json_wrapped_in_prose_or_fences_is_still_parsed()
    {
        $text = "Here is the evaluation:\n```json\n".json_encode([
            'criteria' => [
                'task_completion' => ['score' => 90, 'comment' => 'Complete.'],
                'accuracy' => ['score' => 140, 'comment' => 'Clamped.'],
                'politeness' => ['score' => '75', 'comment' => 'Numeric string.'],
                // clarity missing on purpose
            ],
            'better_answer' => 'Dear Mr Smith, ...',
            'summary' => 'Well done.',
        ])."\n```\nHope that helps!";

        Http::fake(['*' => Http::response($this->rawReply($text))]);

        $evaluation = (new AnthropicAiProvider(apiKey: 'k', model: 'm'))->evaluateWriting(['id' => 'i1'], 'x');

        $this->assertSame(90, $evaluation->criteria['task_completion']['score']);
        $this->assertSame(100, $evaluation->criteria['accuracy']['score']);
        $this->assertSame(75, $evaluation->criteria['politeness']['score']);
        $this->assertSame(0, $evaluation->criteria['clarity']['score']);
        $this->assertSame('', $evaluation->criteria['clarity']['comment']);
        $this->assertSame('Dear Mr Smith, ...', $evaluation->betterAnswer);
    }

    public function test_a_reply_that_is_not_json_is_an_error()
    {
        Http::fake(['*' => Http::response($this->rawReply('Sorry, I cannot do that.'))]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('did not return a JSON object');

        (new AnthropicAiProvider(apiKey: 'k', model: 'm'))->generateLexicon('Towel', LexiconKind::Word, '');
    }

    public function test_a_refusal_stop_reason_is_an_error_not_a_reply()
    {
        Http::fake(['*' => Http::response([
            'content' => [['type' => 'text', 'text' => '{"reply": "..."}']],
            'stop_reason' => 'refusal',
            'model' => 'm',
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('declined');

        (new AnthropicAiProvider(apiKey: 'k', model: 'm'))->roleplayReply($this->scenario(), []);
    }

    public function test_a_missing_model_fails_before_any_request_is_sent()
    {
        Http::fake();

        try {
            (new AnthropicAiProvider(apiKey: 'k', model: ''))->generateLexicon('Towel', LexiconKind::Word, '');
            $this->fail('No exception for a blank model.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('AI_MODEL', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_role_play_turns_map_onto_user_and_assistant_messages()
    {
        Http::fake(['*' => Http::response($this->reply(['reply' => 'Yes, it\'s John Miller.']))]);

        $reply = (new AnthropicAiProvider(apiKey: 'k', model: 'm'))->roleplayReply($this->scenario(), [
            ['role' => 'guest', 'text' => 'Hello! I have a reservation for tonight.', 'at' => 't1'],
            ['role' => 'employee', 'text' => 'Good evening. May I have your name, please?', 'at' => 't2'],
        ]);

        $this->assertSame("Yes, it's John Miller.", $reply->text);

        Http::assertSent(function (Request $request): bool {
            $messages = $request['messages'];

            // The API needs a user turn first; the guest opened, so a neutral opener is prepended.
            $this->assertSame('user', $messages[0]['role']);
            $this->assertSame('assistant', $messages[1]['role']);
            $this->assertSame('Hello! I have a reservation for tonight.', $messages[1]['content']);
            $this->assertSame('user', $messages[2]['role']);
            $this->assertSame('Good evening. May I have your name, please?', $messages[2]['content']);

            // The scenario brief comes from the row, never from the client (RP-04).
            $this->assertStringContainsString('Check-in', $request['system']);
            $this->assertStringContainsString('Stay in character', $request['system']);

            return true;
        });
    }

    public function test_an_empty_transcript_starts_with_a_neutral_user_opener()
    {
        Http::fake(['*' => Http::response($this->reply(['reply' => 'Hello! I have a reservation for tonight.']))]);

        (new AnthropicAiProvider(apiKey: 'k', model: 'm'))->roleplayReply($this->scenario(), []);

        Http::assertSent(function (Request $request): bool {
            $this->assertCount(1, $request['messages']);
            $this->assertSame('user', $request['messages'][0]['role']);

            return true;
        });
    }

    public function test_a_plain_text_reply_is_used_as_the_guest_line()
    {
        Http::fake(['*' => Http::response($this->rawReply('Hello! I have a reservation for tonight.'))]);

        $reply = (new AnthropicAiProvider(apiKey: 'k', model: 'm'))->roleplayReply($this->scenario(), []);

        $this->assertSame('Hello! I have a reservation for tonight.', $reply->text);
    }

    public function test_the_evaluation_asks_for_the_scenario_criteria_and_parses_them()
    {
        Http::fake(['*' => Http::response($this->reply([
            'criteria' => [
                'pronunciation' => ['score' => 70, 'comment' => 'ok'],
                'grammar' => ['score' => 60, 'comment' => 'ok'],
                'vocabulary' => ['score' => 80, 'comment' => 'ok'],
                'fluency' => ['score' => 70, 'comment' => 'ok'],
                'politeness' => ['score' => 80, 'comment' => 'ok'],
            ],
            'overall' => 72,
            'did_well' => ['You greeted the guest politely.', 42, ''],
            'improve' => [['title' => 'Room information', 'text' => 'Be clearer.'], 'junk'],
            'better_expression' => ['yours' => 'You are in room fifth floor.', 'better' => 'Your room is on the fifth floor.'],
            'key_phrase' => '“Your room is on the fifth floor.”',
            'summary_label' => 'Good try!',
            'summary_text' => 'Keep practicing.',
            'footnote' => 'Use complete sentences.',
        ]))]);

        $evaluation = (new AnthropicAiProvider(apiKey: 'k', model: 'm'))->evaluateRoleplay($this->scenario(), [
            ['role' => 'guest', 'text' => 'Hello!', 'at' => 't1'],
            ['role' => 'employee', 'text' => 'Good evening.', 'at' => 't2'],
        ]);

        $this->assertSame(['pronunciation' => 70, 'grammar' => 60, 'vocabulary' => 80, 'fluency' => 70, 'politeness' => 80], $evaluation->criteriaScores());
        $this->assertSame(72, $evaluation->overall);
        $this->assertSame(['You greeted the guest politely.'], $evaluation->didWell);
        $this->assertSame([['title' => 'Room information', 'text' => 'Be clearer.']], $evaluation->improve);
        $this->assertSame('Use complete sentences.', $evaluation->footnote);

        Http::assertSent(function (Request $request): bool {
            $this->assertStringContainsString('"politeness": {"score"', $request['system']);
            $this->assertStringContainsString("Guest: Hello!\nEmployee: Good evening.", $request['messages'][0]['content']);

            return true;
        });
    }

    // -------------------------------------------------------------- helpers

    /**
     * A Messages API response whose single text block is the given JSON.
     *
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function reply(array $json, int $inputTokens = 10, int $outputTokens = 5): array
    {
        return $this->rawReply((string) json_encode($json, JSON_UNESCAPED_UNICODE), $inputTokens, $outputTokens);
    }

    /**
     * @return array<string, mixed>
     */
    private function rawReply(string $text, int $inputTokens = 10, int $outputTokens = 5): array
    {
        return [
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'configured-model',
            'content' => [['type' => 'text', 'text' => $text]],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => $inputTokens, 'output_tokens' => $outputTokens],
        ];
    }

    private function scenario(): AiScenario
    {
        $scenario = new AiScenario;
        $scenario->forceFill([
            'title' => 'Check-in',
            'slug' => 'check-in',
            'situation' => 'A guest arrives at the front desk with a reservation.',
            'ai_role' => 'A tired guest arriving late in the evening.',
            'employee_role' => 'The receptionist on duty.',
            'objective' => 'Check the guest in politely and give them their key.',
            'goals' => ['Greet the guest', 'Ask for the name'],
            'useful_phrases' => ['How can I help you?'],
            'min_turns' => 4,
            'max_turns' => 12,
        ]);

        return $scenario;
    }
}

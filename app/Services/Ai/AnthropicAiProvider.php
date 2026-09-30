<?php

namespace App\Services\Ai;

use App\Contracts\AiEvaluation;
use App\Contracts\AiProvider;
use App\Contracts\AiReply;
use App\Contracts\AiUsageInfo;
use App\Contracts\ChecksConnection;
use App\Contracts\CoachingSummary;
use App\Contracts\CourseOutline;
use App\Contracts\DashboardBriefingDraft;
use App\Contracts\LessonDraft;
use App\Contracts\LexiconDraft;
use App\Contracts\PronunciationCoaching;
use App\Contracts\PronunciationGuideDraft;
use App\Contracts\ReminderDraft;
use App\Contracts\ScenarioDraft;
use App\Contracts\SpeakingEvaluation;
use App\Contracts\TestQuestionsDraft;
use App\Contracts\TextTranslationDraft;
use App\Contracts\WritingEvaluation;
use App\Enums\Accent;
use App\Enums\EnglishLevel;
use App\Enums\LexiconKind;
use App\Enums\ScenarioDifficulty;
use App\Models\AiScenario;
use App\Services\Ai\Concerns\BuildsBriefingPrompts;
use App\Services\Ai\Concerns\BuildsCoachingPrompts;
use App\Services\Ai\Concerns\BuildsLessonPrompts;
use App\Services\Ai\Concerns\BuildsPronunciationPrompts;
use App\Services\Ai\Concerns\BuildsReminderPrompts;
use App\Services\Ai\Concerns\BuildsRoleplayPrompts;
use App\Services\Ai\Concerns\BuildsTranslationPrompts;
use App\Services\Ai\Concerns\ParsesJsonReplies;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * The Anthropic Messages API through Laravel's HTTP client (API-02, API-04,
 * SEC-03; spec 0003 Part C).
 *
 * Model, endpoint and key all come from config/services.php: nothing here
 * names a model, so the client swaps generations with an .env change. Every
 * request asks for a single JSON object and parses it defensively, because
 * a model reply is data from outside the trust boundary.
 *
 * Request shape (Messages API): POST {base_url}/v1/messages with headers
 * x-api-key + anthropic-version, body {model, max_tokens, system, messages}.
 * The response carries `content[]` blocks (we read the text ones), `usage`
 * (input_tokens / output_tokens) and `stop_reason` (a `refusal` is an error
 * for us, never a reply to a learner).
 */
final class AnthropicAiProvider implements AiProvider, ChecksConnection
{
    use BuildsBriefingPrompts;
    use BuildsCoachingPrompts;
    use BuildsLessonPrompts;
    use BuildsPronunciationPrompts;
    use BuildsReminderPrompts;
    use BuildsRoleplayPrompts;
    use BuildsTranslationPrompts;
    use Concerns\BuildsAssessmentPrompts;
    use ParsesJsonReplies;

    public const PROVIDER = 'anthropic';

    public const DEFAULT_BASE_URL = 'https://api.anthropic.com';

    private const API_VERSION = '2023-06-01';

    /**
     * Generous on purpose: this is a cap, not a cost. A capped reply would
     * be truncated JSON and a failed job.
     */
    private const MAX_TOKENS = 16000;

    private const TIMEOUT_SECONDS = 60;

    private const RETRY_TIMES = 2;

    private const RETRY_SLEEP_MS = 500;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly ?string $baseUrl = null,
        private readonly ?string $fastModel = null,
    ) {}

    /**
     * One tiny request for the Settings → AI models "Test" button (API-04).
     */
    public function ping(bool $fast = false): AiReply
    {
        $result = $this->complete(
            'You are a connection check. Respond with ONLY the JSON object {"ok": true}.',
            [['role' => 'user', 'content' => 'Connection check.']],
            $fast ? $this->fastModel : null,
        );

        return new AiReply(trim($result['text']), $result['usage']);
    }

    public function roleplayReply(AiScenario $scenario, array $transcript, ?EnglishLevel $level = null): AiReply
    {
        // The same prompt as every other provider (RoleplayPrompt): the
        // admin's instruction and guardrails included (RP-04; spec 0005 §2.3).
        // The per-turn pacing line goes out as its own uncached block after
        // the stable brief, so turn two onwards reads the brief from the
        // prompt cache (spec 0005 §5.3).
        $parts = RoleplayPrompt::replySystemParts($scenario, $transcript, $level);

        // A learner is waiting on this line, so it uses the fast model when
        // one is configured (PERF-04).
        $result = $this->complete($parts['stable'], $this->conversationMessages($transcript), $this->fastModel, $parts['turn']);

        try {
            $reply = $this->string($this->decodeJson($result['text']), 'reply', '');
        } catch (RuntimeException) {
            $reply = '';
        }

        if ($reply === '') {
            // Not JSON after all: treat the whole text as the line rather
            // than fail a learner's turn over formatting.
            $reply = trim($result['text']);
        }

        if ($reply === '') {
            throw new RuntimeException('The AI provider returned an empty reply.');
        }

        return new AiReply($reply, $result['usage']);
    }

    public function evaluateRoleplay(AiScenario $scenario, array $transcript, ?EnglishLevel $level = null): AiEvaluation
    {
        $criteria = $scenario->criteriaKeys();

        $result = $this->complete(
            RoleplayPrompt::evaluationSystem($scenario, $criteria, $level),
            $this->evaluationMessages($transcript),
        );

        return $this->roleplayEvaluationFrom($this->decodeJson($result['text']), $criteria, $result['usage']);
    }

    public function generateLexicon(string $english, LexiconKind $kind, string $context): LexiconDraft
    {
        $system = 'You write vocabulary entries for an English course for hotel staff in Algeria whose English level is low and whose first language is Arabic. Keep every explanation to one short, simple sentence. Examples must be realistic hotel situations. Respond with ONLY a JSON object of this exact shape: '
            .'{"arabic_meaning": "<Modern Standard Arabic>", "simple_explanation": "<one simple English sentence>", "hotel_example": "<one English sentence a guest or staff member would say>", "hotel_example_arabic": "<the example in Arabic>", "ipa": "<IPA transcription between slashes or null>", "part_of_speech": "<n|v|adj|adv|phrase or null>"}';

        $messages = [[
            'role' => 'user',
            'content' => sprintf(
                "Entry kind: %s\nEnglish: %s\nContext: %s",
                $kind->value,
                trim($english),
                $context !== '' ? $context : 'general hotel work',
            ),
        ]];

        $result = $this->complete($system, $messages);
        $data = $this->decodeJson($result['text']);

        return new LexiconDraft(
            arabicMeaning: $this->string($data, 'arabic_meaning', ''),
            simpleExplanation: $this->string($data, 'simple_explanation', ''),
            hotelExample: $this->string($data, 'hotel_example', ''),
            hotelExampleArabic: $this->string($data, 'hotel_example_arabic', ''),
            ipa: $this->nullableString($data, 'ipa'),
            partOfSpeech: $this->nullableString($data, 'part_of_speech'),
            usage: $result['usage'],
        );
    }

    public function translateText(string $english, string $language = 'Arabic'): TextTranslationDraft
    {
        $result = $this->complete($this->translationSystemPrompt($language), $this->translationMessages($english));
        $data = $this->decodeJson($result['text']);

        return new TextTranslationDraft(
            // "arabic" is what the prompt asked for before other languages.
            text: $this->string($data, 'translation', $this->string($data, 'arabic', '')),
            usage: $result['usage'],
        );
    }

    public function generateScenario(string $title, string $department, ScenarioDifficulty $difficulty, string $notes = ''): ScenarioDraft
    {
        $system = 'You design AI role-play scenarios for an English course for hotel staff in Algeria whose first language is Arabic and whose English level is low. Given a title, a hotel department and a difficulty, write ONE complete, realistic scenario in which the guest speaks simple, natural English. The employee is practising, so keep the language professional and achievable. Respond with ONLY a JSON object of this exact shape: '
            .'{"description": "<one or two sentences summarising the scenario>", '
            .'"situation": "<2-3 sentences describing the setting the employee faces>", '
            .'"ai_role": "<the guest character and mood, written as \'You are ...\'>", '
            .'"employee_role": "<the employee role, written as \'You are ...\'>", '
            .'"objective": "<one sentence: what the employee must achieve>", '
            .'"goals": ["<short goal>", "<short goal>", "<short goal>", "<short goal>"], '
            .'"useful_phrases": ["<a phrase the employee can use>", "<phrase>", "<phrase>", "<phrase>", "<phrase>"], '
            .'"quote": "<a short opening line the guest might say, in quotation marks>", '
            .'"tip": "<one short coaching tip for the employee>"}';

        $messages = [[
            'role' => 'user',
            'content' => sprintf(
                "Title: %s\nDepartment: %s\nDifficulty: %s\nNotes: %s",
                trim($title),
                $department !== '' ? $department : 'hotel front office',
                $difficulty->value,
                $notes !== '' ? trim($notes) : 'none',
            ),
        ]];

        $result = $this->complete($system, $messages);
        $data = $this->decodeJson($result['text']);

        return new ScenarioDraft(
            description: $this->string($data, 'description', ''),
            situation: $this->string($data, 'situation', ''),
            aiRole: $this->string($data, 'ai_role', ''),
            employeeRole: $this->string($data, 'employee_role', ''),
            objective: $this->string($data, 'objective', ''),
            goals: $this->stringList($data, 'goals'),
            usefulPhrases: $this->stringList($data, 'useful_phrases'),
            quote: $this->nullableString($data, 'quote'),
            tip: $this->nullableString($data, 'tip'),
            usage: $result['usage'],
        );
    }

    public function generateLesson(string $topic, string $department, string $level, string $notes = ''): LessonDraft
    {
        $result = $this->complete($this->lessonSystemPrompt(), $this->lessonMessages($topic, $department, $level, $notes));

        return $this->lessonDraftFrom($this->decodeJson($result['text']), $topic, $result['usage']);
    }

    public function generateCourseOutline(string $brief, string $department, string $level, int $lessonCount): CourseOutline
    {
        $lessonCount = max(1, $lessonCount);
        $result = $this->complete($this->outlineSystemPrompt($lessonCount), $this->outlineMessages($brief, $department, $level));

        return $this->outlineFrom($this->decodeJson($result['text']), $brief, $lessonCount, $result['usage']);
    }

    public function evaluateWriting(array $item, string $answer, ?EnglishLevel $level = null): WritingEvaluation
    {
        $result = $this->complete($this->writingSystemPrompt($level, WritingEvaluation::rubricFor($item)), $this->writingMessages($item, $answer));

        return $this->parseWritingEvaluation($this->decodeJson($result['text']), $result['usage'], WritingEvaluation::rubricFor($item));
    }

    public function evaluateSpeaking(array $item, string $transcript, ?EnglishLevel $level = null): SpeakingEvaluation
    {
        $result = $this->complete($this->speakingSystemPrompt($level), $this->speakingMessages($item, $transcript));

        return $this->parseSpeakingEvaluation($this->decodeJson($result['text']), $result['usage']);
    }

    public function generateTestQuestions(string $department, string $level, array $skills, string $notes = '', array $avoid = []): TestQuestionsDraft
    {
        $result = $this->complete(
            $this->testQuestionsSystemPrompt(),
            $this->testQuestionsMessages($department, $level, $skills, $notes, $avoid),
        );

        return $this->parseTestQuestions($this->decodeJson($result['text']), $skills, $result['usage']);
    }

    public function coachLearner(array $context, ?EnglishLevel $level = null): CoachingSummary
    {
        $result = $this->complete($this->coachingSystemPrompt($level), $this->coachingMessages($context));

        return $this->parseCoachingSummary($this->decodeJson($result['text']), $result['usage']);
    }

    public function briefDashboard(array $context): DashboardBriefingDraft
    {
        $result = $this->complete(
            $this->briefingSystemPrompt(),
            $this->briefingMessages($context),
        );

        return $this->parseBriefing($this->decodeJson($result['text']), $result['usage']);
    }

    public function draftReminder(string $purpose, string $tone, array $variables): ReminderDraft
    {
        $result = $this->complete($this->reminderSystemPrompt($variables), $this->reminderMessages($purpose, $tone));

        return $this->parseReminderDraft($this->decodeJson($result['text']), $variables, $result['usage']);
    }

    public function pronunciationGuide(string $text, Accent $accent): PronunciationGuideDraft
    {
        $result = $this->complete(
            $this->pronunciationGuideSystemPrompt($accent),
            $this->pronunciationGuideMessages($text, $accent),
        );

        return $this->parsePronunciationGuide($this->decodeJson($result['text']), $result['usage']);
    }

    public function coachPronunciation(array $result, array $words, Accent $accent, ?EnglishLevel $level = null): PronunciationCoaching
    {
        // A learner is waiting on this tip: the fast model when configured.
        $reply = $this->complete(
            $this->pronunciationCoachSystemPrompt($accent, $level),
            $this->pronunciationCoachMessages($result),
            $this->fastModel,
        );

        return $this->parsePronunciationCoaching($this->decodeJson($reply['text']), $words, $reply['usage']);
    }

    // ------------------------------------------------------------ transport

    /**
     * One Messages API call. Returns the concatenated text blocks and the
     * usage the API reported.
     *
     * The system prompt goes out as one cached block (spec 0005 §2.3): a
     * role-play resends the same brief every turn, so later turns read it
     * from the cache. A cache hit needs a byte-identical prefix, so anything
     * that changes from turn to turn (`$turnSystem`, the pacing line) is sent
     * as a second block after the cache breakpoint, never inside the cached
     * one (spec 0005 §5.3). A prompt shorter than the model's minimum
     * cacheable length is simply not cached, never an error. No sampling
     * parameters are sent: current models reject `temperature`, and the
     * model id comes from configuration, so consistency comes from the
     * scoring anchors in the prompt instead.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{text: string, usage: AiUsageInfo}
     */
    private function complete(string $system, array $messages, ?string $model = null, string $turnSystem = ''): array
    {
        $model = $model !== null && $model !== '' ? $model : $this->model;

        if ($model === '') {
            throw new RuntimeException('AI_MODEL is not configured (config services.ai.model).');
        }

        if ($this->apiKey === '') {
            throw new RuntimeException('AI_KEY is not configured (config services.ai.key).');
        }

        $systemBlocks = [[
            'type' => 'text',
            'text' => $system,
            'cache_control' => ['type' => 'ephemeral'],
        ]];

        if ($turnSystem !== '') {
            $systemBlocks[] = ['type' => 'text', 'text' => $turnSystem];
        }

        $response = $this->client()->post('/v1/messages', [
            'model' => $model,
            'max_tokens' => self::MAX_TOKENS,
            'system' => $systemBlocks,
            'messages' => $messages,
        ])->throw();

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('The AI provider returned a non-JSON response.');
        }

        if (($data['stop_reason'] ?? null) === 'refusal') {
            throw new RuntimeException('The AI provider declined the request.');
        }

        $text = '';

        foreach ($this->list($data, 'content') as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $text .= $block['text'];
            }
        }

        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];

        return [
            'text' => $text,
            'usage' => new AiUsageInfo(
                // input_tokens counts only the uncached part; the prompt the
                // call actually used is all three (API-03 metering).
                promptTokens: $this->int($usage, 'input_tokens')
                    + $this->int($usage, 'cache_read_input_tokens')
                    + $this->int($usage, 'cache_creation_input_tokens'),
                completionTokens: $this->int($usage, 'output_tokens'),
                model: $this->string($data, 'model', $model),
                provider: self::PROVIDER,
            ),
        ];
    }

    private function client(): PendingRequest
    {
        $baseUrl = rtrim($this->baseUrl !== null && $this->baseUrl !== '' ? $this->baseUrl : self::DEFAULT_BASE_URL, '/');

        return Http::baseUrl($baseUrl)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
            ])
            ->acceptJson()
            ->asJson()
            ->timeout(self::TIMEOUT_SECONDS)
            ->retry(
                self::RETRY_TIMES,
                self::RETRY_SLEEP_MS,
                // Retry what a retry can fix: an unreachable host, a timeout
                // or conflict, a rate limit, an overloaded or failing server
                // (408, 409, 429, 5xx incl. 529). A 400 or 401 fails at once
                // with its message instead of being sent three times.
                fn (Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException && (
                        in_array($e->response->status(), [408, 409, 429], true)
                        || $e->response->serverError()
                    )),
            );
    }
}

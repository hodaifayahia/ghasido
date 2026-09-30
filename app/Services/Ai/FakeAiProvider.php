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
use App\Services\Pronunciation\ReferenceText;

/**
 * The provider the seeder, the tests and every local run use (spec 0003
 * Part C). Deterministic: the same inputs always give the same output, and
 * no network is touched.
 *
 * - Guest replies walk a per-scenario script keyed by scenario slug; the
 *   Check-in script reproduces photo_17/18 (spec G.5), everything else gets
 *   the generic four lines.
 * - The evaluation is exactly the mockup feedback and numbers (spec G.6).
 * - Lexicon drafts and writing verdicts are templated around the input.
 */
final class FakeAiProvider implements AiProvider, ChecksConnection
{
    public const PROVIDER = 'fake';

    public const FALLBACK_SCRIPT = '*';

    /**
     * The line the guest says once a script is exhausted, so a conversation
     * that runs past its script still ends politely.
     */
    public const CLOSING_LINE = 'Thank you so much for your help. Have a nice day!';

    /**
     * Guest lines per scenario slug, in the order the guest says them.
     * Public and static so a seeder or test can add a script for a new
     * scenario without touching this class.
     *
     * @var array<string, list<string>>
     */
    public static array $scripts = [
        'check-in' => [
            'Hello! I have a reservation for tonight.',
            "Yes, it's John Miller.",
            'Great, thank you! What time is breakfast?',
            'Perfect. Thank you!',
        ],
        // The other five seeded scenarios (spec G.5), so every one converses.
        'room-request' => [
            'Hi, could I get some extra towels, please?',
            "It's room 302. Also, is a late check-out possible tomorrow?",
            'Until 2 p.m. would be perfect. Is there a charge for that?',
            'Great, thank you very much!',
        ],
        'handle-a-complaint' => [
            'Excuse me, the air conditioning in my room is not working and it is very hot.',
            "I've tried that already. Can someone come and fix it now?",
            'And if it cannot be repaired today, can I change rooms?',
            'OK. Thank you for your help.',
        ],
        'make-a-reservation' => [
            "Hello, I'd like to book a room for two nights, please.",
            'From the 12th to the 14th of July, a double room.',
            'Is breakfast included in the price?',
            'Perfect. My name is Maria Lopez. Thank you!',
        ],
        'ask-for-information' => [
            'Hello! Could you tell me what time breakfast is served?',
            'Thanks. And is there a swimming pool in the hotel?',
            'How can I get to the city centre from here?',
            'That is very helpful. Thank you!',
        ],
        'spa-booking' => [
            'Hi, I heard you have a spa. What treatments do you offer?',
            'How much is a one-hour massage, and when is the spa open?',
            "I'd like to book a massage for tomorrow afternoon, around 3 p.m.",
            'Wonderful. Thank you so much!',
        ],
        self::FALLBACK_SCRIPT => [
            'Hello! Could you help me with something, please?',
            'Yes, that would be great. Thank you.',
            'One more question, if you don\'t mind. Is that included?',
            'That\'s all I needed. Thank you for your help!',
        ],
    ];

    public function ping(bool $fast = false): AiReply
    {
        return new AiReply('{"ok": true}', AiUsageInfo::none(self::PROVIDER, $fast ? 'fake-fast' : 'fake'));
    }

    public function roleplayReply(AiScenario $scenario, array $transcript, ?EnglishLevel $level = null): AiReply
    {
        $script = self::$scripts[$scenario->slug] ?? self::$scripts[self::FALLBACK_SCRIPT];

        $guestTurns = count(array_filter(
            $transcript,
            static fn (array $turn): bool => $turn['role'] === 'guest',
        ));

        return new AiReply($script[$guestTurns] ?? self::CLOSING_LINE, AiUsageInfo::none());
    }

    public function evaluateRoleplay(AiScenario $scenario, array $transcript, ?EnglishLevel $level = null): AiEvaluation
    {
        return new AiEvaluation(
            criteria: [
                'pronunciation' => ['score' => 70, 'comment' => 'Clear enough to follow. Slow down on longer words.'],
                'grammar' => ['score' => 60, 'comment' => 'Some sentences were incomplete. Use full sentences.'],
                'vocabulary' => ['score' => 80, 'comment' => 'Good use of hotel words like reservation and breakfast.'],
                'fluency' => ['score' => 70, 'comment' => 'A few pauses, but the conversation kept moving.'],
                'politeness' => ['score' => 80, 'comment' => 'Friendly and professional throughout.'],
            ],
            overall: 70,
            didWell: [
                'You greeted the guest politely.',
                "You asked for the guest's name correctly.",
                'You gave the breakfast time clearly.',
                'You used a friendly and professional tone.',
            ],
            improve: [
                [
                    'title' => 'Room information',
                    'text' => 'The sentence was not clear. Try to use a more natural expression.',
                ],
            ],
            betterExpression: [
                'yours' => 'You are in room fifth floor.',
                'better' => 'Your room is on the fifth floor.',
            ],
            keyPhrase: '“Your room is on the fifth floor.”',
            summaryLabel: 'Good try!',
            summaryText: 'Keep practicing. You can try again.',
            usage: AiUsageInfo::none(),
            footnote: 'Use clear and complete sentences. Guests may not understand short or incomplete phrases.',
        );
    }

    public function generateLexicon(string $english, LexiconKind $kind, string $context): LexiconDraft
    {
        $english = trim($english);
        $isWord = $kind === LexiconKind::Word;
        $where = $context !== '' ? $context : 'the hotel';

        return new LexiconDraft(
            arabicMeaning: sprintf('(ترجمة تجريبية) %s', $english),
            simpleExplanation: $isWord
                ? sprintf('"%s" is a word hotel staff use when talking with guests.', $english)
                : sprintf('"%s" is a polite phrase hotel staff say to guests.', $english),
            hotelExample: $isWord
                ? sprintf('At %s, a guest might say: "Excuse me, I have a question about the %s."', $where, strtolower($english))
                : sprintf('At %s, you could say: "%s"', $where, $english),
            hotelExampleArabic: sprintf('(مثال تجريبي) %s', $english),
            ipa: null,
            partOfSpeech: $isWord ? 'n' : 'phrase',
            usage: AiUsageInfo::none(),
        );
    }

    public function translateText(string $english, string $language = 'Arabic'): TextTranslationDraft
    {
        return new TextTranslationDraft(
            text: $language === 'Arabic'
                ? sprintf('(ترجمة تجريبية) %s', trim($english))
                : sprintf('(%s draft) %s', $language, trim($english)),
            usage: AiUsageInfo::none(),
        );
    }

    public function generateScenario(string $title, string $department, ScenarioDifficulty $difficulty, string $notes = ''): ScenarioDraft
    {
        $title = trim($title);
        $dept = $department !== '' ? $department : 'Reception';

        return new ScenarioDraft(
            description: sprintf(
                'A %s role-play for the %s department: %s.',
                $difficulty->value,
                $dept,
                $title !== '' ? strtolower($title) : 'a guest interaction',
            ),
            situation: sprintf('A guest approaches a member of the %s team. They need help and expect a warm, professional reply.', $dept),
            aiRole: 'You are a polite international hotel guest. You speak simple English. You are friendly but you have a clear request.',
            employeeRole: sprintf('You are a %s staff member. Greet the guest, listen carefully and help them in clear, professional English.', $dept),
            objective: 'Help the guest and complete their request politely.',
            goals: [
                'Greet the guest warmly',
                'Understand what the guest needs',
                'Give clear information',
                'Close the conversation politely',
            ],
            usefulPhrases: [
                'How can I help you today?',
                'Of course, I can help you with that.',
                'Let me check that for you.',
                'Is there anything else I can do for you?',
                'Have a pleasant stay.',
            ],
            quote: '“Hello, could you help me, please?”',
            tip: 'Speak slowly and clearly, and smile — a warm tone matters as much as the words.',
            usage: AiUsageInfo::none(),
        );
    }

    public function generateLesson(string $topic, string $department, string $level, string $notes = ''): LessonDraft
    {
        $topic = trim($topic);
        $dept = $department !== '' ? $department : 'Reception';
        $title = $topic !== '' ? $topic : sprintf('%s Essentials', $dept);

        return new LessonDraft(
            title: $title,
            subtitle: sprintf('Practise the English you need for %s.', strtolower($title)),
            situationText: sprintf('You are working at the %s. A guest comes to you and needs help. You want to reply politely and clearly.', $dept),
            situationQuote: '“Good morning! How can I help you today?”',
            vocabulary: [
                ['english' => 'reservation', 'arabic' => 'حجز', 'explanation' => 'A booking a guest made before arriving.', 'example' => 'I have a reservation for tonight.'],
                ['english' => 'luggage', 'arabic' => 'أمتعة', 'explanation' => 'The bags a guest travels with.', 'example' => 'May I help you with your luggage?'],
                ['english' => 'key card', 'arabic' => 'بطاقة المفتاح', 'explanation' => 'The card that opens the room door.', 'example' => 'Here is your key card.'],
                ['english' => 'floor', 'arabic' => 'طابق', 'explanation' => 'A level of the hotel building.', 'example' => 'Your room is on the third floor.'],
                ['english' => 'breakfast', 'arabic' => 'فطور', 'explanation' => 'The morning meal served at the hotel.', 'example' => 'Breakfast is served from 7 a.m.'],
                ['english' => 'checkout', 'arabic' => 'تسجيل المغادرة', 'explanation' => 'When a guest leaves and pays.', 'example' => 'Checkout is at 11 a.m.'],
            ],
            expressions: [
                ['english' => 'How can I help you?', 'arabic' => 'كيف يمكنني مساعدتك؟', 'explanation' => 'A polite way to offer help.', 'example' => 'Good morning! How can I help you?'],
                ['english' => 'One moment, please.', 'arabic' => 'لحظة من فضلك', 'explanation' => 'Ask the guest to wait a short time.', 'example' => 'One moment, please. I will check.'],
                ['english' => 'Here you are.', 'arabic' => 'تفضل', 'explanation' => 'Say this when you give something to a guest.', 'example' => 'Here you are — your key card.'],
                ['english' => 'Enjoy your stay.', 'arabic' => 'أتمنى لك إقامة سعيدة', 'explanation' => 'A warm closing wish.', 'example' => 'Enjoy your stay with us!'],
            ],
            dialogue: [
                ['speaker' => 'Guest', 'text' => 'Hello, I have a reservation for tonight.', 'arabic' => 'مرحبًا، لدي حجز لهذه الليلة.'],
                ['speaker' => 'Staff', 'text' => 'Welcome! May I have your name, please?', 'arabic' => 'أهلًا بك! هل يمكنني معرفة اسمك من فضلك؟'],
                ['speaker' => 'Guest', 'text' => 'Yes, it is John Miller.', 'arabic' => 'نعم، إنه جون ميلر.'],
                ['speaker' => 'Staff', 'text' => 'Thank you. Your room is on the third floor.', 'arabic' => 'شكرًا لك. غرفتك في الطابق الثالث.'],
                ['speaker' => 'Guest', 'text' => 'Great. What time is breakfast?', 'arabic' => 'رائع. متى يُقدَّم الفطور؟'],
                ['speaker' => 'Staff', 'text' => 'Breakfast is from 7 a.m. Enjoy your stay!', 'arabic' => 'الفطور من الساعة السابعة صباحًا. أتمنى لك إقامة سعيدة!'],
            ],
            practice: [
                ['prompt' => 'A guest says: "I have a reservation." What do you reply?', 'options' => ['Welcome! May I have your name, please?', 'Go away.', 'No.', 'I don\'t know.'], 'answer_index' => 0, 'explanation' => 'Greet the guest and ask for their name politely.'],
                ['prompt' => 'Which phrase closes the conversation warmly?', 'options' => ['Hurry up.', 'Enjoy your stay!', 'What?', 'Maybe later.'], 'answer_index' => 1, 'explanation' => '"Enjoy your stay!" is a warm, polite closing.'],
                ['prompt' => "Where is the guest's room?", 'options' => ['In the kitchen.', 'Outside.', 'On the third floor.', 'At the pool.'], 'answer_index' => 2, 'explanation' => 'The staff member said the room is on the third floor.'],
            ],
            usage: AiUsageInfo::none(),
            objectives: [
                'Understand the situation',
                'Use polite phrases with guests',
                'Answer simple guest questions',
            ],
            imagePrompt: sprintf('A realistic photo of a friendly hotel %s employee helping a guest in a modern hotel.', strtolower($dept)),
            listenRepeat: [
                ['text' => 'How can I help you?', 'arabic' => 'كيف يمكنني مساعدتك؟'],
                ['text' => 'May I have your name, please?', 'arabic' => 'هل يمكنني معرفة اسمك من فضلك؟'],
                ['text' => 'Enjoy your stay.', 'arabic' => 'أتمنى لك إقامة سعيدة'],
            ],
        );
    }

    public function generateCourseOutline(string $brief, string $department, string $level, int $lessonCount): CourseOutline
    {
        $brief = trim($brief);
        $dept = $department !== '' ? $department : 'Reception';
        $lessons = [];

        for ($index = 1; $index <= max(1, $lessonCount); $index++) {
            $lessons[] = [
                'title' => sprintf('%s: Part %d', $brief !== '' ? mb_substr($brief, 0, 60) : $dept, $index),
                'topic' => sprintf('Part %d of a %s course: %s', $index, $dept, $brief !== '' ? $brief : 'everyday guest service'),
            ];
        }

        return new CourseOutline(
            title: $brief !== '' ? mb_substr($brief, 0, 80) : sprintf('%s English', $dept),
            description: sprintf('A short %s course for the %s team.', $level !== '' ? $level : 'beginner', $dept),
            lessons: $lessons,
            usage: AiUsageInfo::none(),
        );
    }

    public function evaluateWriting(array $item, string $answer, ?EnglishLevel $level = null): WritingEvaluation
    {
        $information = $item['information'] ?? [];
        $facts = is_array($information)
            ? implode(' ', array_map(static fn (mixed $line): string => is_string($line) ? $line.'.' : '', $information))
            : '';

        $comments = [
            'task_completion' => ['score' => 85, 'comment' => 'You answered the guest\'s question and included the key information.'],
            'accuracy' => ['score' => 75, 'comment' => 'A few small grammar slips. Check verb forms and articles.'],
            'politeness' => ['score' => 80, 'comment' => 'Warm and professional. A closing line would make it even better.'],
            'clarity' => ['score' => 70, 'comment' => 'Keep sentences short so the guest can read them quickly.'],
        ];
        $criteria = [];

        // The item's own rubric when it has one (client report 2026-09-29).
        foreach (WritingEvaluation::rubricFor($item) as $key => $label) {
            $criteria[$key] = [...($comments[$key] ?? ['score' => 78, 'comment' => sprintf('Good work on %s. Keep it short and clear.', strtolower($label))]), 'label' => $label];
        }

        $firstWords = implode(' ', array_slice(preg_split('/\s+/', trim($answer)) ?: [], 0, 4));

        return new WritingEvaluation(
            criteria: $criteria,
            corrections: $firstWords === '' ? [] : [
                ['original' => $firstWords, 'corrected' => 'Dear Guest, thank you for your message.', 'note' => 'Start an email with a polite greeting.'],
            ],
            betterAnswer: trim(sprintf(
                "Dear Guest,\n\nThank you for your message. %s\n\nPlease let us know if you would like to confirm the booking.\n\nKind regards,\nThe Reception Team",
                $facts,
            )),
            summary: 'Good try! Your reply covers the request. Check the grammar and keep your sentences short and clear.',
            usage: AiUsageInfo::none(),
        );
    }

    public function evaluateSpeaking(array $item, string $transcript, ?EnglishLevel $level = null): SpeakingEvaluation
    {
        $heard = trim($transcript) !== '';

        return new SpeakingEvaluation(
            criteria: [
                'task_completion' => ['score' => $heard ? 80 : 10, 'comment' => $heard ? 'You responded to the situation and the guest would understand you.' : 'We could not hear an answer. Try recording again, a little closer to the microphone.'],
                'accuracy' => ['score' => $heard ? 70 : 0, 'comment' => 'Check verb forms; short sentences are fine.'],
                'vocabulary' => ['score' => $heard ? 75 : 0, 'comment' => 'Good hotel words. Add one polite phrase such as "Of course".'],
                'politeness' => ['score' => $heard ? 85 : 0, 'comment' => 'Warm and professional tone.'],
            ],
            betterAnswer: "I'm very sorry about that. I will send housekeeping to your room right away.",
            summary: $heard ? 'Well done! Your answer gets the message across. Keep it short and polite.' : 'No answer was recognised this time.',
            usage: AiUsageInfo::none(),
        );
    }

    /**
     * Deterministic drafts: the first template per skill whose question is
     * not in `$avoid`, so a paired Post-test always gets different items.
     */
    /**
     * A predictable summary built from the figures it was given, so the
     * coaching card works locally without a key (spec 0005 §3.5).
     */
    public function coachLearner(array $context, ?EnglishLevel $level = null): CoachingSummary
    {
        $lessons = is_array($context['lessons'] ?? null) ? $context['lessons'] : [];
        $done = (int) ($lessons['completed'] ?? 0);
        $total = (int) ($lessons['total'] ?? 0);
        $focus = is_string($context['weakest_skill'] ?? null) ? $context['weakest_skill'] : null;

        return new CoachingSummary(
            headline: $done > 0 ? 'Good progress, keep your rhythm going.' : 'A great time to start your training.',
            strengths: [$done > 0 ? sprintf('You have completed %d of %d lessons.', $done, $total) : 'You have everything ready to begin.'],
            focus: [$focus !== null ? sprintf('Spend a little extra time on %s.', $focus) : 'Practise the useful phrases from your next lesson.'],
            tip: 'Try saying "Of course, let me check that for you." with your next guest.',
            usage: AiUsageInfo::none(self::PROVIDER, 'fake'),
        );
    }

    /**
     * A predictable briefing from the figures it was given (spec 0005 §4.1).
     */
    public function briefDashboard(array $context): DashboardBriefingDraft
    {
        $employees = (int) ($context['employees'] ?? 0);
        $atRisk = is_array($context['at_risk'] ?? null) ? (int) ($context['at_risk']['total'] ?? 0) : 0;

        return new DashboardBriefingDraft(
            headline: $employees > 0 ? 'Training is moving; a few learners need a nudge.' : 'No learners in this programme yet.',
            highlights: [sprintf('%d learners are enrolled in the programme.', $employees)],
            concerns: [$atRisk > 0 ? sprintf('%d learners show signs of falling behind.', $atRisk) : 'No learner is currently at risk.'],
            actions: ['Send a friendly reminder to inactive learners this week.', 'Give each department 15 minutes of practice time on shift.'],
            usage: AiUsageInfo::none(self::PROVIDER, 'fake'),
        );
    }

    /**
     * A predictable reminder draft using the first placeholders (spec 0005 §4.2).
     */
    /**
     * A predictable guide (spec 0006 §4): each word as its own "IPA", and a
     * trap for every typical swap its letters allow ("very" → "fery").
     */
    public function pronunciationGuide(string $text, Accent $accent): PronunciationGuideDraft
    {
        $swaps = ['th' => ['s', 'th → s'], 'v' => ['f', 'v → f'], 'p' => ['b', 'p → b']];
        $words = [];

        foreach (ReferenceText::from($text)->tokens as $token) {
            $traps = [];

            foreach ($swaps as $from => [$to, $sound]) {
                $position = strpos($token['key'], $from);

                if ($position !== false) {
                    $traps[] = [
                        'heard_as' => substr_replace($token['key'], $to, $position, strlen($from)),
                        'sound' => $sound,
                        'tip' => sprintf('Practise the %s sound slowly.', $from),
                    ];
                }
            }

            $words[] = [
                'word' => $token['display'],
                'ipa' => '/'.$token['key'].'/',
                'syllables' => $token['key'],
                'sounds_like' => $token['key'],
                'tip' => '',
                'traps' => $traps,
                'homophones' => [],
            ];
        }

        return new PronunciationGuideDraft(
            ipa: '/'.implode(' ', array_column($words, 'syllables')).'/',
            words: $words,
            tips: ['Say it slowly first, then at normal speed.'],
            usage: AiUsageInfo::none(self::PROVIDER, 'fake'),
        );
    }

    /**
     * A predictable coaching from the result it was given (spec 0006 §5).
     */
    public function coachPronunciation(array $result, array $words, Accent $accent, ?EnglishLevel $level = null): PronunciationCoaching
    {
        $tips = [];

        foreach (is_array($result['weak_words'] ?? null) ? $result['weak_words'] : [] as $weak) {
            if (is_array($weak) && is_string($weak['word'] ?? null) && count($tips) < 2) {
                $sound = is_string($weak['sound'] ?? null) ? ' ('.$weak['sound'].')' : '';
                $tips[] = ['word' => $weak['word'], 'tip' => sprintf('Say "%s" slowly and clearly%s.', $weak['word'], $sound)];
            }
        }

        return new PronunciationCoaching(
            headline: 'Good try! A few words need more practice.',
            tips: $tips,
            next: 'Listen to the slow audio, then say the sentence again.',
            arabic: null,
            usage: AiUsageInfo::none(self::PROVIDER, 'fake'),
        );
    }

    public function draftReminder(string $purpose, string $tone, array $variables): ReminderDraft
    {
        $name = in_array('name', $variables, true) ? '{{name}}' : 'there';

        return new ReminderDraft(
            subject: 'A quick English practice today',
            body: sprintf('Hello %s, your next English lesson is ready. Ten minutes today will help you with your guests. Open GHASIDO and continue now.', $name),
            usage: AiUsageInfo::none(self::PROVIDER, 'fake'),
        );
    }

    public function generateTestQuestions(string $department, string $level, array $skills, string $notes = '', array $avoid = []): TestQuestionsDraft
    {
        $used = array_flip($avoid);
        $questions = [];

        foreach ($skills as $index => $skill) {
            foreach (self::testTemplates($skill) as $template) {
                $text = sprintf('%s (%s, Q%d)', $template['question'], $department !== '' ? $department : 'Reception', $index + 1);

                if (isset($used[$text])) {
                    continue;
                }

                $used[$text] = true;
                $questions[] = [...$template, 'skill' => $skill, 'question' => $text];

                break;
            }
        }

        return new TestQuestionsDraft($questions, AiUsageInfo::none());
    }

    /**
     * @return list<array{question: string, situation: string, options: list<array{id: string, text: string}>, correct: string|null, audio_script: string, sentences: list<string>, model_answer: string}>
     */
    private static function testTemplates(string $skill): array
    {
        $blank = ['situation' => '', 'options' => [], 'correct' => null, 'audio_script' => '', 'sentences' => [], 'model_answer' => ''];

        return match ($skill) {
            'listening' => [
                [...$blank, 'question' => 'What does the guest need?', 'audio_script' => 'Hello, could I have two more towels in room 214, please?', 'options' => [['id' => 'A', 'text' => 'More towels'], ['id' => 'B', 'text' => 'A taxi'], ['id' => 'C', 'text' => 'A late check-out']], 'correct' => 'A'],
                [...$blank, 'question' => 'What time does the guest want breakfast?', 'audio_script' => 'Good evening. Can I have breakfast in my room at seven tomorrow?', 'options' => [['id' => 'A', 'text' => 'At six'], ['id' => 'B', 'text' => 'At seven'], ['id' => 'C', 'text' => 'At nine']], 'correct' => 'B'],
                [...$blank, 'question' => 'What is the problem?', 'audio_script' => 'Excuse me, the air conditioning in my room is not working.', 'options' => [['id' => 'A', 'text' => 'The key does not work'], ['id' => 'B', 'text' => 'The room is noisy'], ['id' => 'C', 'text' => 'The air conditioning is broken']], 'correct' => 'C'],
            ],
            'speaking' => [
                [...$blank, 'question' => 'What would you say to the guest?', 'situation' => 'A guest arrives at reception and looks tired after a long trip.', 'model_answer' => 'Good evening, welcome! Let me check you in quickly so you can rest.'],
                [...$blank, 'question' => 'Apologise and offer a solution.', 'situation' => 'A guest says the room is not clean.', 'model_answer' => "I'm very sorry. I will send housekeeping right away."],
                [...$blank, 'question' => 'Explain the breakfast times.', 'situation' => 'A guest asks when breakfast is served.', 'model_answer' => 'Breakfast is served from 7 to 10 a.m. in the restaurant.'],
            ],
            'writing' => [
                [...$blank, 'question' => 'Write a short reply to the guest.', 'situation' => 'A guest emails: "Do you have parking at the hotel?"', 'model_answer' => 'Dear Guest, yes, we have free parking for our guests. Kind regards.'],
                [...$blank, 'question' => 'Write a short reply confirming the booking.', 'situation' => 'A guest emails to book a double room for two nights.', 'model_answer' => 'Dear Guest, your double room is confirmed for two nights. We look forward to welcoming you.'],
                [...$blank, 'question' => 'Write a short reply about the airport shuttle.', 'situation' => 'A guest asks if the hotel has an airport shuttle.', 'model_answer' => 'Dear Guest, yes, our shuttle leaves every hour. Please tell us your arrival time.'],
            ],
            'ordering' => [
                [...$blank, 'question' => 'Put the conversation in the correct order.', 'sentences' => ['Good afternoon. Welcome to the hotel.', 'Hello, I have a reservation.', 'May I have your name, please?', 'It is Ben Ali.', 'Thank you. Here is your key.']],
                [...$blank, 'question' => 'Put the phone call in the correct order.', 'sentences' => ['Good morning, reception. How can I help you?', 'Hello, can I book a table for tonight?', 'Of course. For how many people?', 'For two, please.', 'Perfect, see you tonight.']],
                [...$blank, 'question' => 'Put the check-out conversation in order.', 'sentences' => ['Good morning. I would like to check out.', 'Of course. What is your room number?', 'Room 305.', 'Here is your bill. Did you enjoy your stay?', 'Yes, very much. Thank you!']],
            ],
            default => [
                [...$blank, 'question' => 'A guest asks for the Wi-Fi password. What do you say?', 'options' => [['id' => 'A', 'text' => 'Here it is, it is on this card.'], ['id' => 'B', 'text' => 'I don\'t know.'], ['id' => 'C', 'text' => 'Ask someone else.']], 'correct' => 'A'],
                [...$blank, 'question' => 'Which sentence is the most polite?', 'options' => [['id' => 'A', 'text' => 'Wait.'], ['id' => 'B', 'text' => 'Could you wait a moment, please?'], ['id' => 'C', 'text' => 'Not now.']], 'correct' => 'B'],
                [...$blank, 'question' => 'A guest wants a taxi. What do you say?', 'options' => [['id' => 'A', 'text' => 'No taxis here.'], ['id' => 'B', 'text' => 'Walk to the station.'], ['id' => 'C', 'text' => 'Of course, I will call one for you now.']], 'correct' => 'C'],
            ],
        };
    }
}

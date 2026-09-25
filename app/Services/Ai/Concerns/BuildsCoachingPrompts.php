<?php

namespace App\Services\Ai\Concerns;

use App\Contracts\AiUsageInfo;
use App\Contracts\CoachingSummary;
use App\Enums\EnglishLevel;
use App\Services\Ai\RoleplayPrompt;

/**
 * The learner-coaching prompt every real provider shares (spec 0005 §3.5).
 *
 * The model receives the learner's own figures as data and writes a short,
 * adult, encouraging summary at the learner's level. It is told to use only
 * those figures, so it cannot invent a score, and it never produces a link:
 * the next step on the card is chosen by the server. Needs
 * ParsesJsonReplies on the using class.
 */
trait BuildsCoachingPrompts
{
    protected function coachingSystemPrompt(?EnglishLevel $level): string
    {
        return implode(' ', [
            'You are a warm, adult English coach for hotel staff whose first language is Arabic.',
            'You receive one learner\'s training data as JSON and write a short personal summary for them.',
            RoleplayPrompt::learnerLine($level),
            'Use ONLY the figures in the data; never invent a score, lesson or event. If there is little data, say so kindly and encourage the first steps.',
            'A test percentage, skill figure or level that is absent from the data is one the learner is not shown: never estimate it and never mention the learner\'s level or a test result unless it is in the data.',
            'Speak to the learner as "you". Praise real progress, name one or two concrete things to work on (a skill, a role-play criterion, or phrases that need practice), and give one practical hotel phrase they can use today.',
            'Never mention percentages below 50 as failures; the tone is encouraging, professional and never childish (UX-02, RP-09).',
            'Respond with ONLY a JSON object of this exact shape: {"headline": "<max 12 words>", "strengths": ["<one sentence>", ...1-2 items], "focus": ["<one sentence>", ...1-2 items], "tip": "<one sentence with a useful hotel phrase in quotation marks>"}',
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{role: string, content: string}>
     */
    protected function coachingMessages(array $context): array
    {
        $data = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return [[
            'role' => 'user',
            'content' => "Learner data (JSON):\n".($data === false ? '{}' : $data)."\n\nWrite the summary now.",
        ]];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function parseCoachingSummary(array $data, AiUsageInfo $usage): CoachingSummary
    {
        return new CoachingSummary(
            headline: $this->string($data, 'headline', 'Keep going, you are making progress.'),
            strengths: array_slice($this->stringList($data, 'strengths'), 0, 2),
            focus: array_slice($this->stringList($data, 'focus'), 0, 2),
            tip: $this->string($data, 'tip', ''),
            usage: $usage,
        );
    }
}

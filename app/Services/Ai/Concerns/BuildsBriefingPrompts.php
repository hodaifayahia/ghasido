<?php

namespace App\Services\Ai\Concerns;

use App\Contracts\AiUsageInfo;
use App\Contracts\DashboardBriefingDraft;

/**
 * The dashboard-briefing prompt every real provider shares (spec 0005 §4.1).
 *
 * The model reads aggregate figures only (counts, percentages, department
 * averages, the number of learners each risk rule flagged); no learner name
 * or answer is ever in the data, so none can appear in the briefing
 * (PRIV-03, ROLE-04). Needs ParsesJsonReplies on the using class.
 */
trait BuildsBriefingPrompts
{
    protected function briefingSystemPrompt(): string
    {
        return implode(' ', [
            'You are an analyst for an English training programme for hotel staff.',
            'You receive the programme\'s current figures as JSON and write a short briefing for the manager who reads the dashboard.',
            'Use ONLY the figures in the data; never invent a number, a person or an event. Refer to groups ("3 learners", "Housekeeping"), never to individuals.',
            'Be direct, professional and practical. Each point is one sentence.',
            'Actions must be things a hotel manager can do this week (for example: send a reminder to inactive learners, give a department 15 minutes of practice time, check in with learners who stalled after the Pre-test).',
            'Respond with ONLY a JSON object of this exact shape: {"headline": "<max 14 words>", "highlights": ["<one sentence>", ...1-3 items], "concerns": ["<one sentence>", ...1-3 items], "actions": ["<one sentence>", ...2-3 items]}',
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{role: string, content: string}>
     */
    protected function briefingMessages(array $context): array
    {
        $data = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return [[
            'role' => 'user',
            'content' => "Programme figures (JSON):\n".($data === false ? '{}' : $data)."\n\nWrite the briefing now.",
        ]];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function parseBriefing(array $data, AiUsageInfo $usage): DashboardBriefingDraft
    {
        return new DashboardBriefingDraft(
            headline: $this->string($data, 'headline', 'Training overview'),
            highlights: array_slice($this->stringList($data, 'highlights'), 0, 3),
            concerns: array_slice($this->stringList($data, 'concerns'), 0, 3),
            actions: array_slice($this->stringList($data, 'actions'), 0, 3),
            usage: $usage,
        );
    }
}

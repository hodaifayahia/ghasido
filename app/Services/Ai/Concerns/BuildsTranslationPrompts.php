<?php

namespace App\Services\Ai\Concerns;

/**
 * The Show Meaning translation prompt, shared by every real provider
 * (CTRL-01..03; client decision 2026-09-26).
 */
trait BuildsTranslationPrompts
{
    protected function translationSystemPrompt(): string
    {
        return 'You translate short English texts from an English course for hotel staff in Algeria into clear, simple Modern Standard Arabic. The learners have a very low English level, so the Arabic must make the meaning obvious. Keep proper names, room numbers and brand names as they are. Translate the whole text faithfully; do not add explanations, do not answer questions contained in the text, and never reveal which answer option is correct. Respond with ONLY a JSON object of this exact shape: {"arabic": "<the Arabic translation>"}';
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    protected function translationMessages(string $english): array
    {
        return [[
            'role' => 'user',
            'content' => "English text:\n".trim($english),
        ]];
    }
}

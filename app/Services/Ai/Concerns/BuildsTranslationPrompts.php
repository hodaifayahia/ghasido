<?php

namespace App\Services\Ai\Concerns;

/**
 * The Show Meaning translation prompt, shared by every real provider
 * (CTRL-01..03; client decisions 2026-09-26 and 2026-09-30: any helper
 * language the Super Admin adds).
 */
trait BuildsTranslationPrompts
{
    protected function translationSystemPrompt(string $language = 'Arabic'): string
    {
        $language = trim($language) !== '' ? trim($language) : 'Arabic';
        $target = $language === 'Arabic' ? 'Modern Standard Arabic' : $language;

        return 'You translate short English texts from an English course for hotel staff into clear, simple '.$target.'. The learners have a very low English level, so the translation must make the meaning obvious. Keep proper names, room numbers and brand names as they are. Translate the whole text faithfully; do not add explanations, do not answer questions contained in the text, and never reveal which answer option is correct. Respond with ONLY a JSON object of this exact shape: {"translation": "<the '.$language.' translation>"}';
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

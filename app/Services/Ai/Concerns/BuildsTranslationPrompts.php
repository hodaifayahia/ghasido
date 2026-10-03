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

    /**
     * The system prompt for a batch of interface strings (client request
     * 2026-10-03).
     */
    protected function interfaceTranslationSystemPrompt(string $language): string
    {
        return 'You translate the user interface of GHASIDO, a web platform that teaches English to hotel staff, from English into '.trim($language).'. '
            .'Each value is one interface string: a button, a label, a heading or a short message. Translate it naturally and concisely, as a professional app in that language would say it. '
            .'Rules: keep every placeholder that starts with a colon exactly as written (for example :name, :count, :hotel); keep the | separators and range markers such as {0}, [2,10] and [11,*] exactly; keep GHASIDO, AI, brand names, emails, URLs and numbers unchanged; never add explanations. '
            .'You receive a JSON object of id → English text. Respond with ONLY a JSON object of this exact shape: {"translations": {"<id>": "<translation>"}} containing every id.';
    }

    /**
     * @param  array<string, string>  $strings
     * @return list<array{role: string, content: string}>
     */
    protected function interfaceTranslationMessages(array $strings): array
    {
        return [[
            'role' => 'user',
            'content' => (string) json_encode((object) $strings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $strings
     * @return array<string, string>
     */
    protected function interfaceTranslations(array $data, array $strings): array
    {
        $translations = $data['translations'] ?? [];
        $texts = [];

        if (! is_array($translations)) {
            return [];
        }

        foreach (array_keys($strings) as $id) {
            $value = $translations[$id] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $texts[(string) $id] = trim($value);
            }
        }

        return $texts;
    }
}

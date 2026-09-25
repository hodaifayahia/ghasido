<?php

namespace App\Services\Ai\Concerns;

use App\Contracts\AiUsageInfo;
use App\Contracts\PronunciationCoaching;
use App\Contracts\PronunciationGuideDraft;
use App\Enums\Accent;
use App\Enums\EnglishLevel;
use App\Services\Ai\RoleplayPrompt;
use App\Services\Pronunciation\SpokenWords;

/**
 * The pronunciation prompts every real provider shares (spec 0006 §4, §5):
 * drafting the guide for one text in one accent, and coaching a learner
 * from the structured result of a check. One copy, so the OpenAI-compatible
 * and Anthropic providers cannot drift apart. Needs ParsesJsonReplies.
 *
 * @phpstan-import-type GuideWord from PronunciationGuideDraft
 */
trait BuildsPronunciationPrompts
{
    /**
     * The sounds Arabic-speaking learners of English find hard: the "what we
     * know" both prompts are steered by (spec 0006 §4).
     */
    public const ARABIC_SPEAKER_SOUNDS = 'p and b (Arabic has no /p/, so "parking" becomes "barking"); v and f (no /v/: "very" becomes "fery"); th /θ/ and /ð/ (said s, z, t or d: "think" → "sink", "three" → "tree", "this" → "dis"); ng /ŋ/ (said n or n+g); the short vowels /ɪ/, /e/ and long /iː/ ("ship" / "sheep", "bed" / "bid"); /ʊ/ and /uː/; /ɒ/ and /ɔː/; the schwa /ə/ in unstressed syllables; consonant clusters at the start of a word (a vowel slips in: "espeak", "sitreet"); letters written but not said (silent letters said aloud); and word stress on the wrong syllable.';

    protected function pronunciationAccentLine(Accent $accent): string
    {
        return match ($accent) {
            Accent::British => 'British English (modern Southern British standard: non-rhotic, so "r" is not said before a consonant or at the end of a word; /ɒ/ in "hot"; /ɑː/ in "bath")',
            Accent::American => 'American English (General American: rhotic, every "r" is said; a flapped t in "water"; /æ/ in "bath"; /ɑ/ in "hot")',
        };
    }

    protected function pronunciationGuideSystemPrompt(Accent $accent): string
    {
        return implode(' ', [
            'You are a phonetician and pronunciation coach for '.$this->pronunciationAccentLine($accent).'.',
            'The learners are hotel staff in Algeria whose first language is Arabic (many also speak French) and whose English level is low.',
            'Sounds these learners find hard: '.self::ARABIC_SPEAKER_SOUNDS,
            'For EVERY word of the text, in order, give: "word" exactly as written; "ipa" in this accent between slashes;',
            '"syllables" separated by "·" with the stressed syllable in CAPITALS (a one-syllable word in lowercase);',
            '"sounds_like" a simple respelling a beginner can read, the stressed part in capitals;',
            '"tip" one short sentence on the hardest sound of the word for an Arabic speaker, or "" when the word is easy;',
            '"traps" up to 3 objects {"heard_as", "sound", "tip"}: heard_as is the real English word (or the plausible spelling) a listener or a speech recogniser would hear if the learner makes a typical Arabic-speaker error on this word, e.g. very → ferry, think → sink, parking → barking, three → tree; sound names the swap like "v → f"; tip fixes it in under 15 words; [] when there is none;',
            '"homophones" real English words said exactly like this word in this accent ([] when none).',
            'Also give "ipa" for the whole text and "tips": at most 2 short tips for the whole sentence (linking, rhythm, stress).',
            'Never write Arabic. Respond with ONLY a JSON object of this exact shape:',
            '{"ipa": "/.../", "words": [{"word": "...", "ipa": "/.../", "syllables": "...", "sounds_like": "...", "tip": "...", "traps": [{"heard_as": "...", "sound": "...", "tip": "..."}], "homophones": []}], "tips": ["..."]}',
        ]);
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    protected function pronunciationGuideMessages(string $text, Accent $accent): array
    {
        return [[
            'role' => 'user',
            'content' => sprintf("Accent: %s\nText: %s", $accent->label(), trim($text)),
        ]];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function parsePronunciationGuide(array $data, AiUsageInfo $usage): PronunciationGuideDraft
    {
        $words = [];

        foreach ($this->list($data, 'words') as $row) {
            if (! is_array($row)) {
                continue;
            }

            $word = $this->string($row, 'word', '');

            if ($word === '') {
                continue;
            }

            $traps = [];

            foreach ($this->list($row, 'traps') as $trap) {
                if (! is_array($trap)) {
                    continue;
                }

                $heardAs = $this->string($trap, 'heard_as', '');

                if ($heardAs === '' || SpokenWords::key($heardAs) === SpokenWords::key($word)) {
                    continue;
                }

                $traps[] = [
                    'heard_as' => $heardAs,
                    'sound' => $this->string($trap, 'sound', ''),
                    'tip' => $this->string($trap, 'tip', ''),
                ];
            }

            $words[] = [
                'word' => $word,
                'ipa' => $this->string($row, 'ipa', ''),
                'syllables' => $this->string($row, 'syllables', ''),
                'sounds_like' => $this->string($row, 'sounds_like', ''),
                'tip' => $this->string($row, 'tip', ''),
                'traps' => array_slice($traps, 0, 3),
                'homophones' => array_slice($this->stringList($row, 'homophones'), 0, 5),
            ];
        }

        return new PronunciationGuideDraft(
            ipa: $this->string($data, 'ipa', ''),
            words: array_slice($words, 0, 60),
            tips: array_slice($this->stringList($data, 'tips'), 0, 2),
            usage: $usage,
        );
    }

    protected function pronunciationCoachSystemPrompt(Accent $accent, ?EnglishLevel $level): string
    {
        return implode(' ', [
            'You are a warm, adult English pronunciation coach for hotel staff whose first language is Arabic.',
            RoleplayPrompt::learnerLine($level),
            'The learner practised saying a sentence in '.$accent->label().'. You receive the automatic check as JSON: each word with its status',
            '(correct; unclear = recognised but not clearly; almost = only recognised when the listener expected it; mispronounced = heard as another word, with the sound that changed;',
            'different = heard as an unrelated word; missed = not heard; skipped = not judged), what was heard instead, the scores, hesitations and long pauses, and the guide for the weak words.',
            'Sounds these learners often find hard: '.self::ARABIC_SPEAKER_SOUNDS,
            'Use ONLY this data: never invent a problem the data does not show and never give a score of your own.',
            'Speak to the learner as "you" in short, simple English; adult and encouraging, never childish.',
            'Write "headline" (max 12 words: praise what went well, then name the main fix);',
            '"tips": at most 2 objects {"word", "tip"} for the weakest words, each tip max 18 words and concrete (tongue, lips, teeth, or which syllable to stress; use the guide\'s "sounds like" when it helps);',
            '"next": one short instruction for the next try; "arabic": the main tip in one simple Modern Standard Arabic sentence, or null.',
            'Respond with ONLY a JSON object of this exact shape: {"headline": "...", "tips": [{"word": "...", "tip": "..."}], "next": "...", "arabic": "..."}',
        ]);
    }

    /**
     * @param  array<string, mixed>  $result  CoachPronunciation::context()
     * @return list<array{role: string, content: string}>
     */
    protected function pronunciationCoachMessages(array $result): array
    {
        $data = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return [[
            'role' => 'user',
            'content' => "Pronunciation check (JSON):\n".($data === false ? '{}' : $data)."\n\nWrite the coaching now.",
        ]];
    }

    /**
     * A tip is kept only for a word of the sentence, so the model cannot
     * coach a word the learner never said.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $words  the sentence's words
     */
    protected function parsePronunciationCoaching(array $data, array $words, AiUsageInfo $usage): PronunciationCoaching
    {
        $allowed = array_map(static fn (string $word): string => SpokenWords::key($word), $words);
        $tips = [];

        foreach ($this->list($data, 'tips') as $row) {
            if (! is_array($row)) {
                continue;
            }

            $word = $this->string($row, 'word', '');
            $tip = $this->string($row, 'tip', '');

            if ($word !== '' && $tip !== '' && in_array(SpokenWords::key($word), $allowed, true)) {
                $tips[] = ['word' => $word, 'tip' => $tip];
            }
        }

        return new PronunciationCoaching(
            headline: $this->string($data, 'headline', 'Good try. Listen once more, then say it again.'),
            tips: array_slice($tips, 0, 2),
            next: $this->string($data, 'next', 'Listen to the slow audio, then say the sentence again.'),
            arabic: $this->nullableString($data, 'arabic'),
            usage: $usage,
        );
    }
}

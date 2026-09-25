<?php

namespace App\Services\Stt;

use App\Contracts\SpeechToTextProvider;
use App\Contracts\TranscribedWord;
use App\Contracts\WordTranscript;

/**
 * The speech-to-text stand-in for local runs and tests (spec 0003 Part C).
 * Returns a fixed marker so a voice turn still lands in the transcript.
 *
 * For the pronunciation check (spec 0006) it "hears" the words in
 * `services.stt.fake_words` (free listen) and `services.stt.fake_hinted_words`
 * (hinted listen), each written `word` or `word:confidence`, so a test or a
 * local demo decides what the learner said without any network.
 */
final class FakeSpeechToTextProvider implements SpeechToTextProvider
{
    public const PROVIDER = 'fake';

    public const TRANSCRIPT = '[voice message]';

    /** Each fake word takes this long, with a short gap after it. */
    private const WORD_MS = 320;

    private const GAP_MS = 80;

    public function transcribe(string $absolutePath, string $mime): string
    {
        return self::TRANSCRIPT;
    }

    public function transcribeWords(string $absolutePath, string $mime, array $keyterms = []): WordTranscript
    {
        $configured = config($keyterms === [] ? 'services.stt.fake_words' : 'services.stt.fake_hinted_words');
        $configured = is_string($configured) ? $configured : '';

        $words = [];
        $at = 200;

        foreach (preg_split('/\s+/', trim($configured), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $entry) {
            [$word, $confidence] = array_pad(explode(':', $entry, 2), 2, null);
            $words[] = new TranscribedWord(
                word: $word,
                confidence: is_numeric($confidence) ? (float) $confidence : 0.97,
                startMs: $at,
                endMs: $at + self::WORD_MS,
            );
            $at += self::WORD_MS + self::GAP_MS;
        }

        return new WordTranscript(
            text: implode(' ', array_map(static fn (TranscribedWord $word): string => $word->word, $words)),
            words: $words,
            provider: self::PROVIDER,
            model: 'fake',
            keyterms: $keyterms,
        );
    }
}

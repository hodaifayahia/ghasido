<?php

namespace App\Contracts;

/**
 * A word-level transcript for the pronunciation check (spec 0006 §5).
 *
 * Stored verbatim on `pronunciation_attempts.stt_raw` (toArray), so a retry
 * never pays for the same listen twice and the research data can be
 * re-scored later without the audio (DATA-01).
 */
final readonly class WordTranscript
{
    /**
     * @param  list<TranscribedWord>  $words
     * @param  list<string>  $keyterms  the words the recogniser was hinted with, if any
     */
    public function __construct(
        public string $text,
        public array $words,
        public string $provider,
        public string $model,
        public array $keyterms = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->words === [];
    }

    /**
     * Does any word carry a confidence? An OpenAI-style transcript does not,
     * and the clarity score is then left out (spec 0006 §5).
     */
    public function hasConfidence(): bool
    {
        foreach ($this->words as $word) {
            if ($word->confidence !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{text: string, words: list<array{word: string, confidence: float|null, start_ms: int|null, end_ms: int|null}>, provider: string, model: string, keyterms: list<string>}
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'words' => array_map(static fn (TranscribedWord $word): array => $word->toArray(), $this->words),
            'provider' => $this->provider,
            'model' => $this->model,
            'keyterms' => $this->keyterms,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $words = [];

        foreach (is_array($data['words'] ?? null) ? $data['words'] : [] as $word) {
            if (is_array($word)) {
                $words[] = TranscribedWord::fromArray($word);
            }
        }

        $keyterms = [];
        foreach (is_array($data['keyterms'] ?? null) ? $data['keyterms'] : [] as $term) {
            if (is_string($term)) {
                $keyterms[] = $term;
            }
        }

        return new self(
            text: is_string($data['text'] ?? null) ? $data['text'] : '',
            words: $words,
            provider: is_string($data['provider'] ?? null) ? $data['provider'] : 'unknown',
            model: is_string($data['model'] ?? null) ? $data['model'] : 'unknown',
            keyterms: $keyterms,
        );
    }
}

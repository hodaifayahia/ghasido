<?php

namespace App\Contracts;

/**
 * One word as a speech recogniser heard it (spec 0006 §5): its confidence
 * (0-1, null when the provider gives none) and where it sits in the audio.
 */
final readonly class TranscribedWord
{
    public function __construct(
        public string $word,
        public ?float $confidence = null,
        public ?int $startMs = null,
        public ?int $endMs = null,
    ) {}

    /**
     * @return array{word: string, confidence: float|null, start_ms: int|null, end_ms: int|null}
     */
    public function toArray(): array
    {
        return [
            'word' => $this->word,
            'confidence' => $this->confidence,
            'start_ms' => $this->startMs,
            'end_ms' => $this->endMs,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $confidence = $data['confidence'] ?? null;
        $start = $data['start_ms'] ?? null;
        $end = $data['end_ms'] ?? null;

        return new self(
            word: is_string($data['word'] ?? null) ? $data['word'] : '',
            confidence: is_numeric($confidence) ? (float) $confidence : null,
            startMs: is_numeric($start) ? (int) $start : null,
            endMs: is_numeric($end) ? (int) $end : null,
        );
    }
}

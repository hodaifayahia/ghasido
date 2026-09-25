<?php

namespace App\Services\Pronunciation;

/**
 * The sentence (or word) a learner was asked to say, split into the words
 * that are judged (spec 0006 §5).
 *
 * One token per word the learner sees: split on whitespace, hyphens and
 * slashes ("check-in" is two words to say). Each token keeps the word as
 * displayed and its comparison key. A number is shown but never judged.
 */
final readonly class ReferenceText
{
    /**
     * @param  list<array{display: string, key: string, judged: bool}>  $tokens
     */
    private function __construct(
        public string $text,
        public array $tokens,
    ) {}

    public static function from(string $text): self
    {
        $normalised = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        $parts = preg_split('/[\s\/\-–—]+/u', $normalised, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = [];

        foreach ($parts as $part) {
            $display = preg_replace('/^[\p{P}\p{S}]+|[\p{P}\p{S}]+$/u', '', $part) ?? $part;
            $key = SpokenWords::key($part);

            if ($key === '' || $display === '') {
                continue;
            }

            $tokens[] = [
                'display' => $display,
                'key' => $key,
                'judged' => ! SpokenWords::isNumeric($key),
            ];
        }

        return new self($normalised, $tokens);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_column($this->tokens, 'key');
    }

    public function count(): int
    {
        return count($this->tokens);
    }

    public function isEmpty(): bool
    {
        return $this->tokens === [];
    }

    /**
     * The words to hint the recogniser with on the second listen: judged,
     * at least three letters, each once (spec 0006 §2 finding 5).
     *
     * @return list<string>
     */
    public function keyterms(): array
    {
        $terms = [];

        foreach ($this->tokens as $token) {
            if ($token['judged'] && mb_strlen($token['key']) >= 3 && ! in_array($token['key'], $terms, true)) {
                $terms[] = $token['key'];
            }
        }

        return array_slice($terms, 0, 40);
    }
}

<?php

namespace App\Services\Ai\Concerns;

use RuntimeException;

/**
 * Defensive parsing of a model's JSON reply (spec 0003 Part C).
 *
 * A provider reply is data from outside the trust boundary: it may be prose
 * around a JSON object, a truncated object, or the wrong types. Every reader
 * here narrows to the expected type and falls back rather than trusting the
 * model. Shared by every real AiProvider so the rules never diverge.
 */
trait ParsesJsonReplies
{
    /**
     * Find the JSON object in a model reply. Tolerates prose or code fences
     * around it; throws when there is no object at all.
     *
     * @return array<string, mixed>
     */
    protected function decodeJson(string $text): array
    {
        $candidates = [trim($text)];

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidates[] = substr($text, $start, $end - $start + 1);
        }

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                return $decoded;
            }
        }

        throw new RuntimeException('The AI provider did not return a JSON object.');
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    protected function string(array $data, string $key, string $default): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    protected function nullableString(array $data, string $key): ?string
    {
        $value = $this->string($data, $key, '');

        return $value === '' || strtolower($value) === 'null' ? null : $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    protected function int(array $data, string $key): int
    {
        $value = $data[$key] ?? 0;

        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
    }

    /**
     * A 0-100 score, clamped.
     *
     * @param  array<array-key, mixed>  $data
     */
    protected function score(array $data, string $key): int
    {
        return max(0, min(100, $this->int($data, $key)));
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<mixed>
     */
    protected function list(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    protected function stringList(array $data, string $key): array
    {
        $items = [];
        foreach ($this->list($data, $key) as $item) {
            if (is_string($item) && trim($item) !== '') {
                $items[] = trim($item);
            }
        }

        return $items;
    }

    /**
     * Rows of a vocabulary or expression list: an English item with its
     * Arabic meaning, a simple explanation and a hotel example. Rows without
     * an English word are dropped.
     *
     * @param  array<array-key, mixed>  $data
     * @return list<array{english: string, arabic: string, explanation: string, example: string}>
     */
    protected function lexiconRows(array $data, string $key): array
    {
        $rows = [];
        foreach ($this->list($data, $key) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $english = $this->string($row, 'english', '');
            if ($english === '') {
                continue;
            }

            $rows[] = [
                'english' => $english,
                'arabic' => $this->string($row, 'arabic', ''),
                'explanation' => $this->string($row, 'explanation', ''),
                'example' => $this->string($row, 'example', ''),
            ];
        }

        return $rows;
    }

    /**
     * Rows of a dialogue: a speaker, the English line and its Arabic. Lines
     * without text are dropped.
     *
     * @param  array<array-key, mixed>  $data
     * @return list<array{speaker: string, text: string, arabic: string}>
     */
    protected function dialogueRows(array $data, string $key): array
    {
        $rows = [];
        foreach ($this->list($data, $key) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $text = $this->string($row, 'text', '');
            if ($text === '') {
                continue;
            }

            $rows[] = [
                'speaker' => $this->string($row, 'speaker', 'Staff'),
                'text' => $text,
                'arabic' => $this->string($row, 'arabic', ''),
            ];
        }

        return $rows;
    }

    /**
     * Rows of a multiple-choice practice set. A row needs a prompt and at
     * least two options; an out-of-range answer index falls back to the
     * first option so a bad reply can never point past the options.
     *
     * @param  array<array-key, mixed>  $data
     * @return list<array{prompt: string, options: list<string>, answer_index: int, explanation: string}>
     */
    protected function practiceRows(array $data, string $key): array
    {
        $rows = [];
        foreach ($this->list($data, $key) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $prompt = $this->string($row, 'prompt', '');
            $options = $this->stringList($row, 'options');
            if ($prompt === '' || count($options) < 2) {
                continue;
            }

            $answer = $this->int($row, 'answer_index');
            if ($answer < 0 || $answer >= count($options)) {
                $answer = 0;
            }

            $rows[] = [
                'prompt' => $prompt,
                'options' => $options,
                'answer_index' => $answer,
                'explanation' => $this->string($row, 'explanation', ''),
            ];
        }

        return $rows;
    }
}

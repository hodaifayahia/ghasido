<?php

namespace App\Services\Learning;

use App\Enums\ActivityType;
use App\Enums\AnswerShape;
use App\Models\ActivityVersion;

/**
 * Scores a raw answer against a frozen activity version (TEST-06, TEST-09,
 * PRAC-04, DATA-01, DATA-11, spec 0003 B.9).
 *
 * Takes the version, never the activity: an admin edit after the answer
 * changes nothing here. Never throws on a malformed or partial answer; a
 * missing item answer is simply wrong, so an abandoned test still scores.
 *
 * The raw answer is keyed by item id. A single item test question may also
 * post its answer bare (not wrapped in the item id); `answerFor()` accepts
 * both.
 */
class ActivityScorer
{
    /**
     * @param  array<array-key, mixed>  $rawAnswer  keyed by item id
     */
    public function score(ActivityVersion $version, array $rawAnswer): ScoreResult
    {
        $type = $version->activity()->firstOrFail()->type;
        $items = $version->items();

        return $this->scoreItems($type, $items, $rawAnswer);
    }

    /**
     * The same, without the activity lookup, for callers that already hold
     * the type (the test finisher scores many at once).
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array<array-key, mixed>  $rawAnswer
     */
    public function scoreItems(ActivityType $type, array $items, array $rawAnswer): ScoreResult
    {
        $ids = array_map(
            static fn (array $item): string => (string) ($item['id'] ?? ''),
            $items,
        );

        if (! $type->isAutoScored()) {
            return ScoreResult::ungraded($ids);
        }

        $perItem = [];

        foreach ($items as $index => $item) {
            $id = $ids[$index];
            $answer = $this->answerFor($rawAnswer, $id, count($items));

            $perItem[$id] = match ($type->answerShape()) {
                AnswerShape::Option => $this->matchesOption($item, $answer),
                AnswerShape::PairMap => $this->matchesPairs($item, $answer),
                AnswerShape::OrderedList => $this->matchesOrder($item, $answer),
                AnswerShape::Typed => $this->matchesAccepted($item['accepted'] ?? null, $answer),
                AnswerShape::Blanks => $this->matchesBlanks($item, $answer),
                AnswerShape::Recording, AnswerShape::Text => null,
            };
        }

        return ScoreResult::graded($perItem);
    }

    /**
     * The answer for one item: `rawAnswer[id]`, or the raw answer itself when
     * a single item question posted it bare (a one element list is unwrapped
     * to the option id; an order list or a pair map is used whole).
     *
     * @param  array<array-key, mixed>  $rawAnswer
     */
    public function answerFor(array $rawAnswer, string $id, int $itemCount): mixed
    {
        if (array_key_exists($id, $rawAnswer)) {
            return $rawAnswer[$id];
        }

        if ($itemCount !== 1 || $rawAnswer === []) {
            return null;
        }

        if (array_is_list($rawAnswer) && count($rawAnswer) === 1) {
            return $rawAnswer[0];
        }

        return $rawAnswer;
    }

    /**
     * Option types: the answer is one option id, compared as a trimmed string.
     *
     * @param  array<string, mixed>  $item
     */
    private function matchesOption(array $item, mixed $answer): bool
    {
        $expected = $item['correct'] ?? null;

        if (! is_scalar($expected) || ! is_scalar($answer)) {
            return false;
        }

        return trim((string) $answer) !== '' && trim((string) $answer) === trim((string) $expected);
    }

    /**
     * Match types: every expected prompt -> target pair must be present.
     *
     * @param  array<string, mixed>  $item
     */
    private function matchesPairs(array $item, mixed $answer): bool
    {
        $expected = $item['pairs'] ?? null;

        if (! is_array($expected) || $expected === [] || ! is_array($answer)) {
            return false;
        }

        foreach ($expected as $prompt => $target) {
            $given = $answer[(string) $prompt] ?? $answer[$prompt] ?? null;

            if (! is_scalar($given) || ! is_scalar($target) || (string) $given !== (string) $target) {
                return false;
            }
        }

        return true;
    }

    /**
     * A typed answer is right when it equals one of the accepted answers,
     * ignoring case, spacing and punctuation (client report 2026-09-29).
     * `{text: "…"}` is accepted as well as a bare string.
     */
    private function matchesAccepted(mixed $accepted, mixed $answer): bool
    {
        if (is_array($answer) && array_key_exists('text', $answer)) {
            $answer = $answer['text'];
        }

        if (! is_array($accepted) || ! is_scalar($answer)) {
            return false;
        }

        $given = self::normaliseTyped((string) $answer);

        if ($given === '') {
            return false;
        }

        foreach ($accepted as $option) {
            if (is_scalar($option) && self::normaliseTyped((string) $option) === $given) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fill in the blank: every blank of the sentence must hold one of its
     * accepted words. The answer maps blank id → typed word.
     *
     * @param  array<string, mixed>  $item
     */
    private function matchesBlanks(array $item, mixed $answer): bool
    {
        $blanks = $item['blanks'] ?? null;

        if (! is_array($blanks) || $blanks === [] || ! is_array($answer)) {
            return false;
        }

        foreach ($blanks as $blank) {
            if (! is_array($blank) || ! is_scalar($blank['id'] ?? null)) {
                return false;
            }

            $id = (string) $blank['id'];

            if (! $this->matchesAccepted($blank['accepted'] ?? null, $answer[$id] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * How a typed answer is compared: lower case, curly quotes made
     * straight, punctuation and symbols dropped, spaces collapsed.
     */
    public static function normaliseTyped(string $text): string
    {
        $text = mb_strtolower(str_replace(['’', '‘', '`'], "'", $text));
        $text = (string) preg_replace('/[\p{P}\p{S}]+/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Order types: the answer is the full list in the expected sequence.
     *
     * @param  array<string, mixed>  $item
     */
    private function matchesOrder(array $item, mixed $answer): bool
    {
        $expected = $item['order'] ?? null;

        if (! is_array($expected) || $expected === [] || ! is_array($answer)) {
            return false;
        }

        $expectedIds = array_map(static fn (mixed $id): string => is_scalar($id) ? (string) $id : '', array_values($expected));
        $givenIds = array_map(static fn (mixed $id): string => is_scalar($id) ? (string) $id : '', array_values($answer));

        return $expectedIds === $givenIds;
    }
}

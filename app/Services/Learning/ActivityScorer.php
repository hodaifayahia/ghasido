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

            if ($type === ActivityType::WordsSentences && is_array($item['accepted_answers'] ?? null)) {
                $perItem[$id] = $this->matchesAcceptedText($item, $answer);

                continue;
            }

            $perItem[$id] = match ($type->answerShape()) {
                AnswerShape::Option => $this->matchesOption($item, $answer),
                AnswerShape::PairMap => $this->matchesPairs($item, $answer),
                AnswerShape::OrderedList => $this->matchesOrder($item, $answer),
                AnswerShape::Text => $type === ActivityType::ShortAnswer
                    ? $this->matchesAcceptedText($item, $answer)
                    : null,
                AnswerShape::Recording => null,
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
    private function answerFor(array $rawAnswer, string $id, int $itemCount): mixed
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

    /**
     * Short-answer questions accept any configured spelling (TEST-05/06).
     *
     * @param  array<string, mixed>  $item
     */
    private function matchesAcceptedText(array $item, mixed $answer): bool
    {
        if (! is_array($answer) || ! is_string($answer['text'] ?? null)) {
            return false;
        }

        $given = mb_strtolower(trim($answer['text']));
        $accepted = $item['accepted_answers'] ?? [];

        if ($given === '' || ! is_array($accepted) || $accepted === []) {
            return false;
        }

        foreach ($accepted as $candidate) {
            if (is_string($candidate) && mb_strtolower(trim($candidate)) === $given) {
                return true;
            }
        }

        return false;
    }
}

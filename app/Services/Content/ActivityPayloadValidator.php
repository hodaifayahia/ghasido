<?php

namespace App\Services\Content;

use App\Enums\ActivityType;
use App\Enums\AnswerShape;

/**
 * Checks that an authored payload can be answered and scored (spec 0003
 * B.9, PRAC-01..03, TEST-06): every option-style item has at least two
 * options and a correct one among them, a matching item pairs real prompts
 * with real targets, an ordering item's order lists each line once.
 *
 * Speaking and writing items are free answers and need nothing more than
 * their id. The messages name the item by its position, which is what the
 * builder shows.
 */
final class ActivityPayloadValidator
{
    /**
     * @param  array<mixed>  $payload
     * @return array<string, string> error key => message
     */
    public static function errors(ActivityType $type, array $payload): array
    {
        $items = $payload['items'] ?? null;

        if (! is_array($items)) {
            return [];
        }

        $errors = [];

        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = 'payload.items.'.$index;
            $number = $index + 1;
            $message = match ($type->answerShape()) {
                AnswerShape::Option => self::option($item, $number),
                AnswerShape::PairMap => self::pairMap($item, $number),
                AnswerShape::OrderedList => self::orderedList($item, $number),
                AnswerShape::Recording, AnswerShape::Text => null,
            };

            if ($message !== null) {
                $errors[$key] = $message;
            }
        }

        return $errors;
    }

    /**
     * @param  array<mixed>  $item
     */
    private static function option(array $item, int $number): ?string
    {
        $ids = self::ids($item['options'] ?? null);

        if (count($ids) < 2) {
            return __('Question :number needs at least two answers.', ['number' => $number]);
        }

        $correct = $item['correct'] ?? null;

        if (! is_string($correct) || ! in_array($correct, $ids, true)) {
            return __('Mark the correct answer of question :number.', ['number' => $number]);
        }

        return null;
    }

    /**
     * @param  array<mixed>  $item
     */
    private static function pairMap(array $item, int $number): ?string
    {
        $prompts = self::ids($item['prompts'] ?? null);
        $targets = self::ids($item['targets'] ?? null);
        $pairs = $item['pairs'] ?? null;

        if ($prompts === [] || count($targets) < 2 || ! is_array($pairs) || $pairs === []) {
            return __('Question :number needs words to match and at least two answers.', ['number' => $number]);
        }

        foreach ($prompts as $prompt) {
            $target = $pairs[$prompt] ?? null;

            if (! is_string($target) || ! in_array($target, $targets, true)) {
                return __('Choose the matching answer for every word of question :number.', ['number' => $number]);
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $item
     */
    private static function orderedList(array $item, int $number): ?string
    {
        $ids = self::ids($item['sentences'] ?? $item['cards'] ?? null);
        $order = $item['order'] ?? null;

        if (count($ids) < 2 || ! is_array($order)) {
            return __('Question :number needs at least two lines to put in order.', ['number' => $number]);
        }

        $sortedIds = $ids;
        $sortedOrder = array_values(array_map('strval', array_filter($order, 'is_scalar')));
        sort($sortedIds);
        sort($sortedOrder);

        if ($sortedIds !== $sortedOrder) {
            return __('The correct order of question :number must list every line once.', ['number' => $number]);
        }

        return null;
    }

    /**
     * The string ids of a list of `{id: …}` entries.
     *
     * @return list<string>
     */
    private static function ids(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        $ids = [];

        foreach ($entries as $entry) {
            if (is_array($entry) && isset($entry['id']) && is_scalar($entry['id']) && (string) $entry['id'] !== '') {
                $ids[] = (string) $entry['id'];
            }
        }

        return array_values(array_unique($ids));
    }
}

<?php

namespace App\Services\Content;

use App\Enums\ActivityType;
use App\Enums\AnswerShape;
use App\Enums\MediaKind;
use App\Models\MediaAsset;

/**
 * Checks that an authored payload can be answered and scored (spec 0003
 * B.9, PRAC-01..03, TEST-06): every option-style item has at least two
 * options and a correct one among them, a matching item pairs real prompts
 * with real targets, an ordering item's order lists each line once.
 *
 * The client's ten types (ActivityType::builderTypes()) are checked
 * further: a media question needs its audio, picture or video; a short
 * answer needs at least one accepted answer; every blank of a fill-in
 * sentence needs its accepted words; matching needs real pairs; speaking
 * and writing need something to respond to. The messages name the item by
 * its position, which is what the builder shows.
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
            $message = $type->isLegacy()
                ? match ($type->answerShape()) {
                    AnswerShape::Option => self::option($item, $number),
                    AnswerShape::PairMap => self::pairMap($item, $number),
                    AnswerShape::OrderedList => self::orderedList($item, $number),
                    default => null,
                }
            : self::builderItem($type, $item, $number);

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
     * One item of the client's ten types (client report 2026-09-29).
     *
     * @param  array<mixed>  $item
     */
    private static function builderItem(ActivityType $type, array $item, int $number): ?string
    {
        $media = self::promptMedia($type, $item, $number);

        if ($media !== null) {
            return $media;
        }

        if ($type === ActivityType::Speaking && ! self::hasPrompt($item, ['question', 'expected_text', 'situation', 'instruction'])) {
            return __('Write what the learner should say or answer in question :number.', ['number' => $number]);
        }

        if ($type === ActivityType::Writing && ! self::hasPrompt($item, ['scenario', 'request_text', 'question'])) {
            return __('Describe the writing task of question :number.', ['number' => $number]);
        }

        return match ($type) {
            ActivityType::MultipleChoice,
            ActivityType::AudioQuestion,
            ActivityType::ImageQuestion,
            ActivityType::VideoQuestion => self::flexibleOptions($item, $number),
            ActivityType::Matching => self::matching($item, $number),
            ActivityType::Ordering => self::ordering($item, $number),
            ActivityType::ShortAnswer => self::shortAnswer($item, $number),
            ActivityType::FillBlank => self::fillBlank($item, $number),
            default => null,
        };
    }

    /**
     * The media a question type is built on, and every media id it names
     * pointing at a real file of the right kind.
     *
     * @param  array<mixed>  $item
     */
    private static function promptMedia(ActivityType $type, array $item, int $number): ?string
    {
        if ($type === ActivityType::AudioQuestion && ! self::isId($item['audio'] ?? null) && ! self::filled($item['audio_text'] ?? null)) {
            return __('Add the audio of question :number: upload, record, choose from the library or write the sentence to play.', ['number' => $number]);
        }

        if ($type === ActivityType::ImageQuestion && ! self::isId($item['image'] ?? null)) {
            return __('Add the picture of question :number.', ['number' => $number]);
        }

        if ($type === ActivityType::VideoQuestion && ! self::isId($item['video'] ?? null)) {
            return __('Add the video of question :number.', ['number' => $number]);
        }

        foreach (['image' => MediaKind::Image, 'audio' => MediaKind::Audio, 'video' => MediaKind::Video, 'poster' => MediaKind::Image] as $key => $kind) {
            $id = $item[$key] ?? null;

            if (! self::isId($id) || MediaAsset::query()->whereKey((int) $id)->where('kind', $kind->value)->exists()) {
                continue;
            }

            if ($kind === MediaKind::Audio) {
                return __('The audio of question :number was not found. Choose it again.', ['number' => $number]);
            }

            if ($kind === MediaKind::Video) {
                return __('The video of question :number was not found. Choose it again.', ['number' => $number]);
            }

            return __('The picture of question :number was not found. Choose it again.', ['number' => $number]);
        }

        if (! self::hasPrompt($item, ['question', 'sentence', 'scenario', 'request_text', 'expected_text', 'situation', 'audio_text'])
            && ! self::isId($item['image'] ?? null)
            && ! self::isId($item['audio'] ?? null)
            && ! self::isId($item['video'] ?? null)) {
            return __('Write the question :number, or add a picture, audio or video.', ['number' => $number]);
        }

        return null;
    }

    /**
     * Two or more answers, each with its text (or its picture when the
     * answers are pictures), and the correct one marked.
     *
     * @param  array<mixed>  $item
     */
    private static function flexibleOptions(array $item, int $number): ?string
    {
        $options = is_array($item['options'] ?? null) ? array_values(array_filter($item['options'], 'is_array')) : [];
        $ids = self::ids($options);

        if (count($ids) < 2) {
            return __('Question :number needs at least two answers.', ['number' => $number]);
        }

        $pictures = ($item['option_style'] ?? 'text') === 'image';

        foreach ($options as $index => $option) {
            $ok = $pictures ? self::isId($option['image'] ?? null) : self::filled($option['text'] ?? null);

            if (! $ok) {
                return $pictures
                    ? __('Add a picture to answer :letter of question :number.', ['letter' => self::letter($index), 'number' => $number])
                    : __('Write answer :letter of question :number.', ['letter' => self::letter($index), 'number' => $number]);
            }
        }

        $correct = $item['correct'] ?? null;

        if (! is_string($correct) || ! in_array($correct, $ids, true)) {
            return __('Mark the correct answer of question :number.', ['number' => $number]);
        }

        return null;
    }

    /**
     * At least two pairs; every left side has its word (or picture / audio),
     * every right side its word or picture, and the pairs map each left
     * side to a real right side.
     *
     * @param  array<mixed>  $item
     */
    private static function matching(array $item, int $number): ?string
    {
        $prompts = is_array($item['prompts'] ?? null) ? array_values(array_filter($item['prompts'], 'is_array')) : [];
        $targets = is_array($item['targets'] ?? null) ? array_values(array_filter($item['targets'], 'is_array')) : [];
        $pairs = $item['pairs'] ?? null;

        if (count($prompts) < 2 || count($targets) < 2 || ! is_array($pairs)) {
            return __('Question :number needs at least two pairs.', ['number' => $number]);
        }

        foreach ($prompts as $prompt) {
            if (! self::filled($prompt['text'] ?? null) && ! self::isId($prompt['image'] ?? null) && ! self::isId($prompt['audio'] ?? null)) {
                return __('Every pair of question :number needs its word on both sides.', ['number' => $number]);
            }
        }

        foreach ($targets as $target) {
            if (! self::filled($target['text'] ?? null) && ! self::isId($target['image'] ?? null)) {
                return __('Every pair of question :number needs its word on both sides.', ['number' => $number]);
            }
        }

        $targetIds = self::ids($targets);

        foreach (self::ids($prompts) as $prompt) {
            $target = $pairs[$prompt] ?? null;

            if (! is_string($target) || ! in_array($target, $targetIds, true)) {
                return __('Choose the matching answer for every word of question :number.', ['number' => $number]);
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $item
     */
    private static function ordering(array $item, int $number): ?string
    {
        $entries = is_array($item['sentences'] ?? null) ? array_values(array_filter($item['sentences'], 'is_array')) : [];

        foreach ($entries as $entry) {
            if (! self::filled($entry['text'] ?? null) && ! self::isId($entry['image'] ?? null)) {
                return __('Write every line of question :number, or remove the empty ones.', ['number' => $number]);
            }
        }

        return self::orderedList($item, $number);
    }

    /**
     * @param  array<mixed>  $item
     */
    private static function shortAnswer(array $item, int $number): ?string
    {
        if (self::acceptedList($item['accepted'] ?? null) === []) {
            return __('Add at least one accepted answer to question :number.', ['number' => $number]);
        }

        return null;
    }

    /**
     * The sentence marks its blanks as `[[id]]`; every blank is listed once
     * with at least one accepted word.
     *
     * @param  array<mixed>  $item
     */
    private static function fillBlank(array $item, int $number): ?string
    {
        $sentence = is_string($item['sentence'] ?? null) ? $item['sentence'] : '';
        preg_match_all('/\[\[([A-Za-z0-9_-]+)\]\]/', $sentence, $matches);
        $tokens = $matches[1];

        if ($tokens === []) {
            return __('Mark at least one blank in the sentence of question :number.', ['number' => $number]);
        }

        if (count($tokens) !== count(array_unique($tokens))) {
            return __('Each blank of question :number may appear only once.', ['number' => $number]);
        }

        $blanks = [];

        foreach (is_array($item['blanks'] ?? null) ? $item['blanks'] : [] as $blank) {
            if (is_array($blank) && is_scalar($blank['id'] ?? null)) {
                $blanks[(string) $blank['id']] = self::acceptedList($blank['accepted'] ?? null);
            }
        }

        $ids = array_keys($blanks);
        $sortedTokens = $tokens;
        sort($ids);
        sort($sortedTokens);

        if ($ids !== $sortedTokens) {
            return __('Give the accepted answers of every blank of question :number.', ['number' => $number]);
        }

        foreach ($blanks as $accepted) {
            if ($accepted === []) {
                return __('Give the accepted answers of every blank of question :number.', ['number' => $number]);
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function acceptedList(mixed $accepted): array
    {
        if (! is_array($accepted)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '', $accepted),
            static fn (string $value): bool => $value !== '',
        ));
    }

    /**
     * @param  array<mixed>  $item
     * @param  list<string>  $keys
     */
    private static function hasPrompt(array $item, array $keys): bool
    {
        foreach ($keys as $key) {
            if (self::filled($item[$key] ?? null)) {
                return true;
            }
        }

        return self::isId($item['image'] ?? null) || self::isId($item['audio'] ?? null) || self::isId($item['video'] ?? null);
    }

    private static function filled(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private static function isId(mixed $value): bool
    {
        return (is_int($value) && $value > 0) || (is_string($value) && ctype_digit($value) && (int) $value > 0);
    }

    private static function letter(int $index): string
    {
        return chr(65 + min(25, $index));
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

<?php

namespace App\Services\Reports;

use App\Enums\ActivityType;
use App\Models\Attempt;

/**
 * Turns a stored answer back into words for a report row (DATA-01, TEST-06,
 * REP-06).
 *
 * The question comes from the frozen activity version the answer refers
 * to (TEST-09, DATA-11), never from the live activity, so a report shows
 * the question the learner actually saw. The raw answer is exported as
 * stored alongside; this only adds a readable rendering of it.
 */
final class AnswerFormatter
{
    /**
     * The question text the learner saw, from the version's item.
     *
     * @param  array<string, mixed>  $item
     */
    public static function question(ActivityType $type, array $item, string $fallback): string
    {
        $text = match ($type) {
            ActivityType::ListenChoose => self::prefixed(__('Listen'), self::string($item, 'audio_text')),
            ActivityType::LookListen => $fallback,
            ActivityType::BestResponse => self::join([self::string($item, 'situation'), self::quoted(self::string($item, 'guest_audio_text'))]),
            ActivityType::ListenMatch => self::prefixed(__('Match'), self::labels(self::list($item, 'prompts'), 'audio_text')),
            ActivityType::WatchRespond => self::join([self::string($item, 'subtitle'), self::string($item, 'question')]),
            ActivityType::WordsSentences => self::string($item, 'sentence'),
            ActivityType::DialogueOrder => self::prefixed(__('Put in order'), self::labels(self::list($item, 'sentences'), 'text')),
            ActivityType::PictureOrder => self::prefixed(__('Put in order'), self::string($item, 'context')),
            ActivityType::MultipleChoice => self::string($item, 'question'),
            ActivityType::Speaking => self::join([self::string($item, 'situation'), self::string($item, 'question')]),
            ActivityType::Writing => self::string($item, 'scenario'),
        };

        return $text === '' ? $fallback : $text;
    }

    /**
     * The learner's answer in words: the option they chose, the pairs they
     * matched, the order they gave, the text they wrote, or the recording.
     *
     * @param  array<string, mixed>  $item
     */
    public static function answer(ActivityType $type, array $item, mixed $rawAnswer, ?string $transcript = null): string
    {
        if ($rawAnswer === null) {
            return '';
        }

        return match ($type) {
            ActivityType::ListenChoose,
            ActivityType::LookListen,
            ActivityType::BestResponse,
            ActivityType::WatchRespond,
            ActivityType::WordsSentences,
            ActivityType::MultipleChoice => self::option($item, $rawAnswer),
            ActivityType::ListenMatch => self::pairs($item, $rawAnswer),
            ActivityType::DialogueOrder => self::ordered(self::list($item, 'sentences'), 'text', $rawAnswer),
            ActivityType::PictureOrder => self::ordered(self::list($item, 'cards'), 'caption', $rawAnswer),
            ActivityType::Speaking => self::recording($rawAnswer, $transcript),
            ActivityType::Writing => is_array($rawAnswer) ? self::string($rawAnswer, 'text') : self::scalar($rawAnswer),
        };
    }

    /**
     * The item of the version this answer refers to, by the key of the raw
     * answer (every test question has one item; practice answers are keyed
     * per item too).
     *
     * @return array{0: array<string, mixed>, 1: mixed}
     */
    public static function itemAndAnswer(Attempt $attempt): array
    {
        $version = $attempt->activityVersion;
        $items = $version === null ? [] : $version->items();
        $raw = $attempt->raw_answer ?? [];

        foreach ($items as $item) {
            $id = $item['id'] ?? null;

            if (is_string($id) && array_key_exists($id, $raw)) {
                return [$item, $raw[$id]];
            }
        }

        $first = $items[0] ?? [];
        $answer = $raw === [] ? null : reset($raw);

        return [$first, $answer];
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>  $item
     */
    private static function option(array $item, mixed $chosen): string
    {
        $id = self::scalar($chosen);

        foreach (self::list($item, 'options') as $option) {
            if ((string) ($option['id'] ?? '') !== $id) {
                continue;
            }

            $text = self::string($option, 'text') !== '' ? self::string($option, 'text') : self::string($option, 'label');

            return $text === '' ? $id : $id.' — '.$text;
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function pairs(array $item, mixed $chosen): string
    {
        if (! is_array($chosen)) {
            return self::scalar($chosen);
        }

        $prompts = self::byId(self::list($item, 'prompts'), 'audio_text');
        $targets = self::byId(self::list($item, 'targets'), 'label');
        $parts = [];

        foreach ($chosen as $promptId => $targetId) {
            $promptId = (string) $promptId;
            $targetId = self::scalar($targetId);
            $parts[] = ($prompts[$promptId] ?? $promptId).' → '.($targets[$targetId] ?? $targetId);
        }

        return implode('; ', $parts);
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private static function ordered(array $entries, string $key, mixed $chosen): string
    {
        if (! is_array($chosen)) {
            return self::scalar($chosen);
        }

        $texts = self::byId($entries, $key);
        $parts = [];

        foreach ($chosen as $id) {
            $id = self::scalar($id);
            $parts[] = $texts[$id] ?? $id;
        }

        return implode(' → ', $parts);
    }

    private static function recording(mixed $raw, ?string $transcript): string
    {
        $duration = is_array($raw) && is_numeric($raw['duration_ms'] ?? null)
            ? sprintf('%.1f s', ((float) $raw['duration_ms']) / 1000)
            : null;

        $label = $duration === null ? __('Voice recording') : __('Voice recording (:duration)', ['duration' => $duration]);

        $text = $transcript ?? (is_array($raw) ? self::string($raw, 'transcript') : '');

        return $text === '' ? $label : $label.': '.$text;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, string>
     */
    private static function byId(array $entries, string $key): array
    {
        $map = [];

        foreach ($entries as $entry) {
            $map[(string) ($entry['id'] ?? '')] = self::string($entry, $key);
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private static function labels(array $entries, string $key): string
    {
        return implode(' · ', array_filter(array_map(
            static fn (array $entry): string => self::string($entry, $key),
            $entries,
        )));
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<array<string, mixed>>
     */
    private static function list(array $item, string $key): array
    {
        $value = $item[$key] ?? [];

        if (! is_array($value)) {
            return [];
        }

        /** @var list<array<string, mixed>> $entries */
        $entries = array_values(array_filter($value, 'is_array'));

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function string(array $item, string $key): string
    {
        $value = $item[$key] ?? null;

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function scalar(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '' : $encoded;
    }

    private static function prefixed(string $prefix, string $text): string
    {
        return $text === '' ? '' : $prefix.': '.$text;
    }

    private static function quoted(string $text): string
    {
        return $text === '' ? '' : '“'.$text.'”';
    }

    /**
     * @param  list<string>  $parts
     */
    private static function join(array $parts): string
    {
        return implode(' ', array_filter($parts, static fn (string $part): bool => $part !== ''));
    }
}

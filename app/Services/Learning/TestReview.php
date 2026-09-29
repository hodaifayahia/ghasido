<?php

namespace App\Services\Learning;

use App\Enums\ActivityType;
use App\Enums\AnswerShape;
use App\Enums\TestType;
use App\Models\ActivityPlacement;
use App\Models\Attempt;
use App\Models\Test;
use App\Models\TestAttempt;

/**
 * What the result page shows after a finished sitting beyond the score: the
 * answer review (`show_answers`) and the closing message
 * (`motivational_message`).
 *
 * The review is built only for a finished sitting and only when the test
 * shows answers and results at all (TEST-03, TEST-04); otherwise no correct
 * answer ever leaves the server. Every question is read against the version
 * the learner's answer referred to (TEST-09, DATA-11), in the order the
 * learner saw it.
 */
class TestReview
{
    /** Keys that hold the ordered entries of an ordering item. */
    private const array ORDER_KEYS = ['sentences', 'cards', 'lines', 'steps'];

    public function __construct(
        private readonly TestRunner $runner,
        private readonly ActivityPresenter $presenter,
        private readonly ActivityScorer $scorer,
    ) {}

    /**
     * One row per question: the learner's answer, the correct answer and a
     * verdict (`correct`, `incorrect`, `unanswered`, or `evaluated` for a
     * spoken or written answer the AI judges separately).
     *
     * @return list<array{number: int, question: string, yourAnswer: string|null, correctAnswer: string|null, status: string}>
     */
    public function rows(Test $test, TestAttempt $attempt): array
    {
        $answers = $attempt->answers()->with('activityVersion')->get()->keyBy('activity_id');
        $rows = [];

        foreach ($this->runner->questions($test, $attempt)->values() as $index => $placement) {
            /** @var Attempt|null $row */
            $row = $answers->get($placement->activity_id);
            $rows[] = $this->row($index + 1, $placement, $row);
        }

        return $rows;
    }

    /**
     * A short, adult, encouraging closing line, based on completion (never
     * on the score, so it suits a hidden result too).
     */
    public function motivationalMessage(Test $test, TestAttempt $attempt): string
    {
        $total = $test->learnerQuestions()->count();
        $answered = $attempt->answers()->whereNotNull('raw_answer')->count();

        if ($total > 0 && $answered < $total) {
            return __('Thank you for taking the test. Every question you tried is a step forward — keep practising and your confidence will grow.');
        }

        if ($test->type === TestType::Post) {
            return __('You have completed your training. Be proud of how far you have come, and use your English with confidence at work every day.');
        }

        return __('Well done for completing the test. This is your starting point — every lesson from here will build your confidence with guests.');
    }

    /**
     * @return array{number: int, question: string, yourAnswer: string|null, correctAnswer: string|null, status: string}
     */
    private function row(int $number, ActivityPlacement $placement, ?Attempt $row): array
    {
        $activity = $placement->activity()->firstOrFail();
        $version = $row->activityVersion ?? $this->presenter->currentVersion($activity);
        $items = $version->items();
        $shape = $activity->type->answerShape();
        /** @var array<array-key, mixed> $raw */
        $raw = is_array($row?->raw_answer) ? $row->raw_answer : [];

        $given = [];
        $correct = [];

        foreach ($items as $item) {
            $answer = $this->scorer->answerFor($raw, (string) ($item['id'] ?? ''), count($items));
            $given[] = $this->given($shape, $item, $answer);
            $correct[] = $this->correct($shape, $item);
        }

        $yourAnswer = self::join($given);

        return [
            'number' => $number,
            'question' => self::questionText($items) ?? $placement->effectivePrompt(),
            'yourAnswer' => $yourAnswer,
            'correctAnswer' => $activity->type->isAutoScored() ? self::join($correct) : null,
            'status' => self::status($activity->type, $row, $yourAnswer),
        ];
    }

    private static function status(ActivityType $type, ?Attempt $row, ?string $yourAnswer): string
    {
        if ($row === null || $yourAnswer === null) {
            return 'unanswered';
        }

        if (! $type->isAutoScored()) {
            return 'evaluated';
        }

        return $row->is_correct === true ? 'correct' : 'incorrect';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function given(AnswerShape $shape, array $item, mixed $answer): ?string
    {
        if ($answer === null || $answer === '' || $answer === []) {
            return null;
        }

        return match ($shape) {
            AnswerShape::Option => is_scalar($answer) ? self::entryLabel($item['options'] ?? null, (string) $answer) : null,
            AnswerShape::PairMap => is_array($answer) ? self::pairsText($item, $answer) : null,
            AnswerShape::OrderedList => is_array($answer) ? self::orderText($item, $answer) : null,
            AnswerShape::Typed => self::typedText($answer),
            AnswerShape::Blanks => is_array($answer) ? self::join(array_map(self::typedText(...), array_values($answer)), ', ') : null,
            AnswerShape::Text => self::typedText($answer),
            AnswerShape::Recording => self::recordingLabel(),
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function correct(AnswerShape $shape, array $item): ?string
    {
        return match ($shape) {
            AnswerShape::Option => is_scalar($item['correct'] ?? null) ? self::entryLabel($item['options'] ?? null, (string) $item['correct']) : null,
            AnswerShape::PairMap => is_array($item['pairs'] ?? null) ? self::pairsText($item, $item['pairs']) : null,
            AnswerShape::OrderedList => is_array($item['order'] ?? null) ? self::orderText($item, $item['order']) : null,
            AnswerShape::Typed => self::firstAccepted($item['accepted'] ?? null),
            AnswerShape::Blanks => self::blanksText($item['blanks'] ?? null),
            AnswerShape::Text, AnswerShape::Recording => null,
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<array-key, mixed>  $pairs
     */
    private static function pairsText(array $item, array $pairs): ?string
    {
        $parts = [];

        foreach ($pairs as $prompt => $target) {
            if (! is_scalar($target)) {
                continue;
            }

            $parts[] = self::entryLabel($item['prompts'] ?? null, (string) $prompt).' → '.self::entryLabel($item['targets'] ?? null, (string) $target);
        }

        return self::join($parts, ', ');
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<array-key, mixed>  $ids
     */
    private static function orderText(array $item, array $ids): ?string
    {
        $entries = null;

        foreach (self::ORDER_KEYS as $key) {
            if (is_array($item[$key] ?? null)) {
                $entries = $item[$key];

                break;
            }
        }

        $parts = [];

        foreach (array_values($ids) as $index => $id) {
            if (is_scalar($id)) {
                $parts[] = ($index + 1).'. '.self::entryLabel($entries, (string) $id);
            }
        }

        return self::join($parts, ' ');
    }

    private static function recordingLabel(): string
    {
        $label = __('Your recording was saved.');

        return is_string($label) ? $label : 'Your recording was saved.';
    }

    private static function typedText(mixed $answer): ?string
    {
        if (is_array($answer) && array_key_exists('text', $answer)) {
            $answer = $answer['text'];
        }

        return is_scalar($answer) && trim((string) $answer) !== '' ? (string) $answer : null;
    }

    private static function firstAccepted(mixed $accepted): ?string
    {
        $first = is_array($accepted) ? ($accepted[0] ?? null) : null;

        return is_scalar($first) ? (string) $first : null;
    }

    private static function blanksText(mixed $blanks): ?string
    {
        if (! is_array($blanks)) {
            return null;
        }

        $words = [];

        foreach ($blanks as $blank) {
            $words[] = is_array($blank) ? self::firstAccepted($blank['accepted'] ?? null) : null;
        }

        return self::join($words, ', ');
    }

    /**
     * The readable text of the entry with this id in a list of options,
     * prompts, targets or sentences; the id itself when it has none.
     */
    private static function entryLabel(mixed $entries, string $id): string
    {
        if (is_array($entries)) {
            foreach ($entries as $entry) {
                if (! is_array($entry) || ! is_scalar($entry['id'] ?? null) || (string) $entry['id'] !== $id) {
                    continue;
                }

                foreach (['text', 'label', 'caption', 'audio_text'] as $key) {
                    if (is_string($entry[$key] ?? null) && trim($entry[$key]) !== '') {
                        return $entry[$key];
                    }
                }
            }
        }

        return $id;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private static function questionText(array $items): ?string
    {
        $first = $items[0] ?? [];

        foreach (['question', 'sentence', 'situation'] as $key) {
            if (is_string($first[$key] ?? null) && trim($first[$key]) !== '') {
                // A fill-in sentence marks its blanks as [[b1]].
                return (string) preg_replace('/\[\[[^\]]*\]\]/', '____', $first[$key]);
            }
        }

        return null;
    }

    /**
     * @param  array<int, string|null>  $parts
     */
    private static function join(array $parts, string $glue = '; '): ?string
    {
        $parts = array_values(array_filter($parts, static fn (?string $part): bool => $part !== null && $part !== ''));

        return $parts === [] ? null : implode($glue, $parts);
    }
}

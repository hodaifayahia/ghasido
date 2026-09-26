<?php

namespace App\Services\Tests;

/**
 * Reads Pre/Post-test questions from a CSV file or pasted rows (TEST-05,
 * TSTM-01; client request 2026-09-26).
 *
 * One question per row. The columns are, in this order or by header name:
 * `type, question, option_a … option_f, correct`. Excel's "CSV" (comma) and
 * the semicolon files it writes in French locales are both accepted. Every
 * row is checked before anything is saved; the result carries either the
 * questions or the list of problems with their line numbers.
 */
final class QuestionImport
{
    public const int MAX_ROWS = 200;

    /** @var list<string> */
    public const array OPTION_COLUMNS = ['option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'option_f'];

    /** Question kinds an import may create; media questions need a file first. */
    private const array KINDS = ['multiple_choice', 'true_false', 'fill_blank', 'short_answer', 'speaking', 'ordering'];

    private const array ALIASES = [
        'mcq' => 'multiple_choice',
        'multiple_choice' => 'multiple_choice',
        'multiplechoice' => 'multiple_choice',
        'choice' => 'multiple_choice',
        'true_false' => 'true_false',
        'truefalse' => 'true_false',
        'true_or_false' => 'true_false',
        'tf' => 'true_false',
        'fill_blank' => 'fill_blank',
        'fill_in_the_blank' => 'fill_blank',
        'fill_the_blank' => 'fill_blank',
        'blank' => 'fill_blank',
        'vocabulary' => 'fill_blank',
        'short_answer' => 'short_answer',
        'writing' => 'short_answer',
        'open' => 'short_answer',
        'speaking' => 'speaking',
        'oral' => 'speaking',
        'ordering' => 'ordering',
        'order' => 'ordering',
    ];

    /**
     * @return array{questions: list<array{kind: string, text: string, options: list<array{id: string, text: string, correct: bool}>}>, errors: list<string>}
     */
    public function parse(string $content): array
    {
        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));

        if ($lines === []) {
            return ['questions' => [], 'errors' => [__('The file is empty.')]];
        }

        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $rows = array_map(static fn (string $line): array => str_getcsv($line, $delimiter, '"', ''), $lines);

        $columns = ['type', 'question', ...self::OPTION_COLUMNS, 'correct'];
        $firstLine = 1;
        $header = array_map(fn (mixed $cell): string => $this->key((string) $cell), $rows[0]);

        if (in_array('question', $header, true)) {
            $columns = $header;
            array_shift($rows);
            $firstLine = 2;
        }

        if (count($rows) > self::MAX_ROWS) {
            return ['questions' => [], 'errors' => [__('A file can hold at most :max questions.', ['max' => self::MAX_ROWS])]];
        }

        $questions = [];
        $errors = [];

        foreach ($rows as $index => $cells) {
            $line = $index + $firstLine;
            $row = [];

            foreach ($columns as $position => $column) {
                $row[$column] = trim((string) ($cells[$position] ?? ''));
            }

            $result = $this->question($row);

            if (is_string($result)) {
                $errors[] = __('Line :line: :problem', ['line' => $line, 'problem' => $result]);
            } else {
                $questions[] = $result;
            }
        }

        if ($questions === [] && $errors === []) {
            $errors[] = __('No questions were found in the file.');
        }

        return ['questions' => $questions, 'errors' => $errors];
    }

    /** The template the admin downloads and fills in. */
    public static function template(): string
    {
        $rows = [
            ['type', 'question', ...self::OPTION_COLUMNS, 'correct'],
            ['multiple_choice', 'A guest asks for a late checkout. What do you say?', 'Of course, let me check that for you.', 'No.', 'Go away.', '', '', '', 'A'],
            ['true_false', 'Breakfast is usually served in the restaurant.', '', '', '', '', '', '', 'A'],
            ['fill_blank', 'Could you please ___ this form?', 'fill in', 'eat', 'sleep', '', '', '', 'A'],
            ['short_answer', 'Write a short, polite reply to a guest who lost their key card.', '', '', '', '', '', '', ''],
            ['speaking', 'Greet a guest who arrives at the front desk.', '', '', '', '', '', '', ''],
            ['ordering', 'Put the check-in steps in the right order.', 'Greet the guest', 'Ask for the booking name', 'Check the ID', 'Give the key card', '', '', ''],
        ];

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF".$csv;
    }

    /**
     * @param  array<string, string>  $row
     * @return array{kind: string, text: string, options: list<array{id: string, text: string, correct: bool}>}|string
     */
    private function question(array $row): array|string
    {
        $type = $this->key($row['type'] ?? '');
        $kind = self::ALIASES[$type] ?? ($type === '' ? 'multiple_choice' : null);

        if ($kind === null) {
            return __('":type" is not a question type an import can create. Use one of: :types.', [
                'type' => $row['type'] ?? '',
                'types' => implode(', ', self::KINDS),
            ]);
        }

        $text = $row['question'] ?? '';

        if ($text === '') {
            return __('The question text is missing.');
        }

        if (mb_strlen($text) > 500) {
            return __('The question is longer than 500 characters.');
        }

        $texts = [];

        foreach (self::OPTION_COLUMNS as $position => $column) {
            $value = $row[$column] ?? '';

            if ($value === '') {
                continue;
            }

            if (mb_strlen($value) > 300) {
                return __('Option :letter is longer than 300 characters.', ['letter' => chr(65 + $position)]);
            }

            $texts[] = $value;
        }

        $correct = strtoupper(trim($row['correct'] ?? ''));

        if ($kind === 'true_false') {
            $texts = $texts === [] ? ['True', 'False'] : $texts;
            $correct = match ($correct) {
                'TRUE', 'T', 'VRAI' => 'A',
                'FALSE', 'F', 'FAUX' => 'B',
                default => $correct,
            };
        }

        $options = [];

        foreach ($texts as $position => $value) {
            $id = chr(65 + $position);
            $options[] = ['id' => $id, 'text' => $value, 'correct' => in_array($kind, ['multiple_choice', 'true_false', 'fill_blank'], true) && $id === $correct];
        }

        if (in_array($kind, ['multiple_choice', 'true_false', 'fill_blank', 'ordering'], true) && count($options) < 2) {
            return __('This question needs at least two options.');
        }

        if (in_array($kind, ['multiple_choice', 'true_false', 'fill_blank'], true)) {
            if ($correct === '') {
                return __('The correct answer is missing: put the letter of the right option (A, B, C…).');
            }

            if (! in_array(true, array_column($options, 'correct'), true)) {
                return __('The correct answer ":correct" is not one of the options.', ['correct' => $row['correct'] ?? '']);
            }
        }

        if (in_array($kind, ['short_answer', 'speaking'], true)) {
            $options = [];
        }

        return ['kind' => $kind, 'text' => $text, 'options' => $options];
    }

    private function key(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = (string) preg_replace('/[^a-z0-9]+/', '_', $value);

        return trim($value, '_');
    }
}

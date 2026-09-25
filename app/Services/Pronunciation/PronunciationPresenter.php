<?php

namespace App\Services\Pronunciation;

use App\Enums\GenerationStatus;
use App\Models\PronunciationAttempt;
use App\Services\Audio\AudioLibrary;

/**
 * One pronunciation check as the step page reads it while polling (spec
 * 0006 §5): the word verdicts as soon as the check is done, the coach's
 * words a moment later. Every word carries icon-ready status and text, never
 * colour alone (ACC-02); weak words carry their own normal / slow audio for
 * the drill once generated.
 *
 * A provider error is never shown to the learner: the stored reason is for
 * the admin, the page gets a plain retry message.
 */
class PronunciationPresenter
{
    /** @var array<string, string> */
    public const LEVEL_LABELS = [
        'excellent' => 'Excellent',
        'good' => 'Good',
        'fair' => 'Keep practising',
        'try_again' => 'Try again',
        PronunciationOutcome::LEVEL_NOT_HEARD => 'Not heard',
    ];

    public function __construct(private readonly AudioLibrary $audio) {}

    /**
     * @return array<string, mixed>
     */
    public function present(PronunciationAttempt $attempt): array
    {
        $words = [];
        $weak = [];

        foreach ($attempt->words ?? [] as $word) {
            $status = is_string($word['status'] ?? null) ? $word['status'] : PronunciationOutcome::MISSED;
            $text = is_string($word['text'] ?? null) ? $word['text'] : '';

            $words[] = [
                'index' => is_int($word['index'] ?? null) ? $word['index'] : count($words),
                'text' => $text,
                'status' => $status,
                'heard' => is_string($word['heard'] ?? null) ? $word['heard'] : null,
                'sound' => is_string($word['sound'] ?? null) ? $word['sound'] : null,
                'tip' => is_string($word['tip'] ?? null) ? $word['tip'] : null,
            ];

            if (! in_array($status, [PronunciationOutcome::CORRECT, PronunciationOutcome::SKIPPED], true)) {
                $weak[] = $text;
            }
        }

        $audio = $weak === [] ? [] : $this->audio->urlsFor($weak, $attempt->lesson?->accent);

        foreach ($words as $position => $word) {
            $words[$position]['audio'] = $audio[$word['text']] ?? null;
        }

        return [
            'id' => $attempt->id,
            'status' => $attempt->status->value,
            'settled' => $attempt->isSettled(),
            'error' => $attempt->status === GenerationStatus::Failed
                ? __('We could not check this recording. Please try again.')
                : null,
            'text' => $attempt->reference_text,
            'sentence' => $attempt->sentence_text,
            'isDrill' => $attempt->isWordDrill(),
            'wordIndex' => $attempt->word_index,
            'accent' => $attempt->accent->value,
            'attemptNo' => $attempt->attempt_no,
            'score' => self::number($attempt->score),
            'level' => $attempt->level,
            'levelLabel' => $attempt->level === null ? null : (self::LEVEL_LABELS[$attempt->level] ?? null),
            'scores' => [
                'words' => self::number($attempt->words_score),
                'clarity' => self::number($attempt->clarity_score),
                'flow' => self::number($attempt->flow_score),
            ],
            'words' => $words,
            'extras' => array_values(array_filter(array_map(
                static fn (array $extra): ?string => is_string($extra['word'] ?? null) ? $extra['word'] : null,
                $attempt->extras ?? [],
            ))),
            'fillers' => $attempt->fillers,
            'longPauses' => $attempt->long_pauses,
            'feedbackStatus' => $attempt->feedback_status,
            'feedback' => $attempt->feedback,
            'pollUrl' => route('learn.pronunciation.show', $attempt),
        ];
    }

    private static function number(?string $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}

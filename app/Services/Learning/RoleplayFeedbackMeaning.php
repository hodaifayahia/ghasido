<?php

namespace App\Services\Learning;

use App\Models\RoleplayAttempt;
use App\Services\Meaning\HelperLanguages;
use App\Services\Meaning\MeaningTexts;
use App\Services\Meaning\MeaningTranslations;
use Throwable;

/**
 * Role-play feedback in the learner's own helper language too (client
 * request 2026-11-05: "keep it in English and add their language as well").
 *
 * The English feedback stays exactly as the AI wrote it (AIE-04, RP-11).
 * Each comment is offered through Show Meaning under the English, like every
 * other learner text (CTRL-01..03, client decision 2026-09-26). A tap never
 * calls the AI, so the translations are drafted here, once, when the
 * evaluation is saved: queued TranslateText jobs, metered like every other
 * translation (AIL-04, API-03), in the learner's helper language. A text
 * already translated (or written by an admin) is never paid for twice.
 */
final class RoleplayFeedbackMeaning
{
    public function __construct(
        private readonly MeaningTranslations $translations,
        private readonly HelperLanguages $languages,
    ) {}

    /**
     * The English texts of a stored feedback block the learner's feedback
     * page shows: the summary, what went well, what to improve and the
     * better expression. The learner's own words ("yours") are not
     * translated: they are the learner's.
     *
     * @param  array<string, mixed>|null  $feedback
     * @return list<string>
     */
    public static function texts(?array $feedback): array
    {
        if ($feedback === null) {
            return [];
        }

        $texts = [
            $feedback['summary_label'] ?? null,
            $feedback['summary_text'] ?? null,
        ];

        foreach (is_array($feedback['did_well'] ?? null) ? $feedback['did_well'] : [] as $item) {
            $texts[] = $item;
        }

        foreach (is_array($feedback['improve'] ?? null) ? $feedback['improve'] : [] as $item) {
            if (is_array($item)) {
                $texts[] = $item['title'] ?? null;
                $texts[] = $item['text'] ?? null;
            }
        }

        $better = $feedback['better_expression'] ?? null;

        if (is_array($better)) {
            $texts[] = $better['better'] ?? null;
        }

        return MeaningTexts::clean($texts);
    }

    /**
     * Queue the drafts for one evaluated learner attempt. Returns how many
     * were queued. Never throws: the evaluation is already saved and must
     * not be lost over a translation (RP-11).
     */
    public function queue(RoleplayAttempt $attempt): int
    {
        // An admin preview is no learner's record (RP-13).
        if ($attempt->is_preview || ! MeaningTranslations::autoTranslate()) {
            return 0;
        }

        $learner = $attempt->user;

        if ($learner === null) {
            return 0;
        }

        $texts = self::texts($attempt->feedback);

        if ($texts === []) {
            return 0;
        }

        try {
            return $this->translations->queue($texts, $learner, [$this->languages->forUser($learner)]);
        } catch (Throwable $e) {
            report($e);

            return 0;
        }
    }
}

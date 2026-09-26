<?php

namespace App\Services\Meaning;

use App\Enums\GenerationStatus;
use App\Jobs\TranslateText;
use App\Models\AuditLog;
use App\Models\TextTranslation;
use App\Models\User;

/**
 * Where Show Meaning translations come from (user request 2026-09-26): an AI
 * draft made once when the content is written, or the admin's own text.
 * A learner's tap only reads; it never calls the AI.
 *
 * Sources: `ai` (a draft, queued or done), `manual` (written or corrected
 * by an admin; the AI never overwrites it) and `requested` (a learner tapped
 * a text nobody has translated yet; it waits for the admin, no AI call).
 */
final class MeaningTranslations
{
    public const string SOURCE_AI = 'ai';

    public const string SOURCE_MANUAL = 'manual';

    public const string SOURCE_REQUESTED = 'requested';

    /**
     * Queue one AI draft for each text that has no translation yet (or whose
     * draft failed). Done and hand-written ones are left alone, so a text is
     * never paid for twice. Returns how many were queued.
     *
     * @param  list<string>  $texts
     */
    public function queue(array $texts, ?User $by = null): int
    {
        $queued = 0;

        foreach (MeaningTexts::clean($texts) as $text) {
            $translation = TextTranslation::query()->firstOrNew(['hash' => TextTranslation::hashOf($text)]);

            $needsDraft = ! $translation->exists
                || $translation->source === self::SOURCE_REQUESTED
                || ($translation->source === self::SOURCE_AI && $translation->status === GenerationStatus::Failed);

            if (! $needsDraft) {
                continue;
            }

            $translation->fill([
                'source_text' => $translation->source_text ?? $text,
                'status' => GenerationStatus::Pending,
                'source' => self::SOURCE_AI,
                'failed_reason' => null,
                'requested_by' => $translation->requested_by ?? $by?->id,
            ])->save();

            TranslateText::dispatch($translation->id);
            $queued++;
        }

        return $queued;
    }

    /** Whether content saves should queue drafts on their own. */
    public static function autoTranslate(): bool
    {
        return (bool) config('guesvia.meaning.auto_translate', true);
    }

    /**
     * A learner tapped a text with no translation: note it for the admin,
     * without calling the AI.
     */
    public function request(string $text, User $learner): TextTranslation
    {
        $text = TextTranslation::normalise($text);

        return TextTranslation::query()->firstOrCreate(
            ['hash' => TextTranslation::hashOf($text)],
            [
                'source_text' => $text,
                'status' => GenerationStatus::Pending,
                'source' => self::SOURCE_REQUESTED,
                'requested_by' => $learner->id,
            ],
        );
    }

    /** The admin's own Arabic; the AI never replaces it (SEC-06 audit). */
    public function write(TextTranslation $translation, string $arabic, User $admin): TextTranslation
    {
        $before = $translation->arabic;

        $translation->forceFill([
            'arabic' => trim($arabic),
            'status' => GenerationStatus::Done,
            'source' => self::SOURCE_MANUAL,
            'failed_reason' => null,
            'updated_by' => $admin->id,
        ])->save();

        AuditLog::record($translation, 'meaning.updated', [
            'text' => $translation->source_text,
            'from' => $before,
            'to' => $translation->arabic,
        ]);

        return $translation;
    }
}

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

    public function __construct(private readonly HelperLanguages $languages) {}

    /**
     * Queue one AI draft for each text that has no translation yet (or whose
     * draft failed), in every active helper language or the ones given.
     * Done and hand-written ones are left alone, so a text is never paid for
     * twice. Returns how many were queued.
     *
     * @param  list<string>  $texts
     * @param  list<string>|null  $locales
     */
    public function queue(array $texts, ?User $by = null, ?array $locales = null): int
    {
        $queued = 0;

        foreach ($locales ?? $this->languages->activeCodes() as $locale) {
            $queued += $this->queueIn($texts, $locale, $by);
        }

        return $queued;
    }

    /**
     * @param  list<string>  $texts
     */
    private function queueIn(array $texts, string $locale, ?User $by): int
    {
        $queued = 0;

        foreach (MeaningTexts::clean($texts) as $text) {
            $translation = self::find($text, $locale);

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
     * The row for a text in one language, new (unsaved) when there is none.
     */
    public static function find(string $text, string $locale): TextTranslation
    {
        $text = TextTranslation::normalise($text);

        return TextTranslation::query()->firstOrNew(
            ['hash' => TextTranslation::hashOf($text), 'locale' => $locale],
            ['source_text' => $text],
        );
    }

    /**
     * A learner tapped a text with no translation: note it for the admin,
     * without calling the AI.
     */
    public function request(string $text, User $learner, string $locale): TextTranslation
    {
        $text = TextTranslation::normalise($text);

        return TextTranslation::query()->firstOrCreate(
            ['hash' => TextTranslation::hashOf($text), 'locale' => $locale],
            [
                'source_text' => $text,
                'status' => GenerationStatus::Pending,
                'source' => self::SOURCE_REQUESTED,
                'requested_by' => $learner->id,
            ],
        );
    }

    /** The admin's own translation; the AI never replaces it (SEC-06 audit). */
    public function write(TextTranslation $translation, string $text, User $admin): TextTranslation
    {
        $before = $translation->translation;

        $translation->forceFill([
            'translation' => trim($text),
            'status' => GenerationStatus::Done,
            'source' => self::SOURCE_MANUAL,
            'failed_reason' => null,
            'updated_by' => $admin->id,
        ])->save();

        AuditLog::record($translation, 'meaning.updated', [
            'locale' => $translation->locale,
            'text' => $translation->source_text,
            'from' => $before,
            'to' => $translation->translation,
        ]);

        return $translation;
    }
}

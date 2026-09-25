<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * One AI-written insight about a learner or a hotel (spec 0005 §3.5, §4.1).
 *
 * Written only by a queued job, read by the page, never shown to anyone
 * outside the subject's scope: a learner reads their own coaching; a
 * hotel's briefing is read inside that hotel (ROLE-02).
 *
 * @property int $id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $kind
 * @property int|null $hotel_id
 * @property GenerationStatus $status
 * @property array<string, mixed>|null $payload
 * @property string|null $fingerprint
 * @property string|null $failed_reason
 * @property Carbon|null $generated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'subject_type',
    'subject_id',
    'kind',
    'hotel_id',
    'status',
    'payload',
    'fingerprint',
    'failed_reason',
    'generated_at',
])]
class AiInsight extends Model
{
    /** A learner's coaching summary (spec 0005 §3.5). */
    public const KIND_LEARNER_COACH = 'learner_coach';

    /** A hotel's dashboard briefing (spec 0005 §4.1). */
    public const KIND_HOTEL_BRIEFING = 'hotel_briefing';

    /** The subject type of the platform-wide briefing (no model behind it). */
    public const SUBJECT_PORTFOLIO = 'portfolio';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'payload' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function isRunning(): bool
    {
        return $this->status === GenerationStatus::Pending || $this->status === GenerationStatus::Running;
    }

    /**
     * The insight for one subject and kind, if any.
     */
    public static function for(Model $subject, string $kind): ?self
    {
        return self::forSubject($subject->getMorphClass(), (int) $subject->getKey(), $kind);
    }

    public static function forSubject(string $type, int $id, string $kind): ?self
    {
        return self::query()
            ->where('subject_type', $type)
            ->where('subject_id', $id)
            ->where('kind', $kind)
            ->first();
    }

    /**
     * Should a job rewrite this insight now (spec 0005 §3.5, §4.1)?
     *
     * Yes when there is none; when the data behind it changed and the last
     * one is older than the cooldown; when a job was lost (running for 30
     * minutes); or an hour after a failure. Never on every page view.
     */
    public static function needsRefresh(?self $insight, string $fingerprint, int $cooldownHours): bool
    {
        if ($insight === null) {
            return true;
        }

        if ($insight->isRunning()) {
            return $insight->updated_at !== null && $insight->updated_at->lte(Date::now()->subMinutes(30));
        }

        if ($insight->status === GenerationStatus::Failed) {
            return $insight->updated_at !== null && $insight->updated_at->lte(Date::now()->subHour());
        }

        if ($insight->fingerprint === $fingerprint) {
            return false;
        }

        return $insight->generated_at === null
            || $insight->generated_at->lte(Date::now()->subHours($cooldownHours));
    }

    /**
     * The card state a page shows for an insight.
     */
    public static function stateOf(?self $insight): string
    {
        $payload = $insight === null ? [] : ($insight->payload ?? []);

        return match (true) {
            $insight === null => 'empty',
            $insight->isRunning() && $payload === [] => 'pending',
            $insight->status === GenerationStatus::Failed && $payload === [] => 'failed',
            $payload === [] => 'empty',
            default => $insight->isRunning() ? 'refreshing' : 'ready',
        };
    }
}

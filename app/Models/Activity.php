<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Enums\ContentStatus;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One answerable item, usable in a lesson or a test (PRAC-01..07, TEST-05,
 * TEST-09, DATA-11, WRITE-05, spec 0003 B.5, B.9).
 *
 * Versioning is not optional. Creating an activity writes version 1; every
 * later change to `payload` or `scoring` bumps `current_version` and writes a
 * new activity_versions row (see booted()). Attempts always point at a
 * version row, so an admin edit can never rewrite what a learner answered.
 *
 * @property int $id
 * @property ActivityType $type
 * @property string|null $skill_label
 * @property string|null $title
 * @property string $prompt
 * @property string|null $prompt_arabic
 * @property array<string, mixed> $payload
 * @property array<string, mixed>|null $scoring
 * @property int|null $time_limit_seconds
 * @property int $attempts_allowed
 * @property bool $show_meaning_enabled
 * @property int $current_version
 * @property int|null $department_id
 * @property int|null $hotel_id
 * @property ContentStatus $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'type',
    'skill_label',
    'title',
    'prompt',
    'prompt_arabic',
    'payload',
    'scoring',
    'time_limit_seconds',
    'attempts_allowed',
    'show_meaning_enabled',
    'department_id',
    'hotel_id',
    'status',
    'created_by',
])]
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'payload' => 'array',
            'scoring' => 'array',
            'time_limit_seconds' => 'integer',
            'attempts_allowed' => 'integer',
            'show_meaning_enabled' => 'boolean',
            'current_version' => 'integer',
            'status' => ContentStatus::class,
        ];
    }

    protected static function booted(): void
    {
        // A content change is a new version, never an edit of the old one
        // (DATA-11, TEST-09).
        static::saving(function (Activity $activity): void {
            if (! $activity->exists) {
                // The column default is 1, but a default never reaches the
                // in-memory model, and the saved hook below needs the number.
                $activity->current_version = max(1, (int) $activity->getAttribute('current_version'));

                return;
            }

            if ($activity->contentChanged()) {
                $activity->current_version = $activity->current_version + 1;
            }
        });

        static::saved(function (Activity $activity): void {
            if ($activity->wasRecentlyCreated || $activity->wasChanged(['payload', 'scoring'])) {
                $activity->writeCurrentVersion();
            }
        });
    }

    /**
     * Has the answerable content changed since it was loaded?
     */
    public function contentChanged(): bool
    {
        // MySQL's JSON type returns object keys in its own order, so a value
        // read back and saved unchanged would look "dirty" to Eloquent and
        // mint a new version for nothing. Compare the content, not the key
        // order (DATA-11).
        foreach (['payload', 'scoring'] as $key) {
            if ($this->isDirty($key)
                && self::canonical($this->getOriginal($key)) !== self::canonical($this->getAttribute($key))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The same value with every object's keys sorted, lists left in order.
     */
    public static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::canonical(...), $value);

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    /**
     * Snapshot the live payload and scoring as the current version row.
     *
     * Idempotent on (activity, version): calling it twice for one version
     * writes one row, which is what lets a seeder call it safely.
     */
    public function writeCurrentVersion(): ActivityVersion
    {
        return $this->versions()->firstOrCreate(
            ['version' => $this->current_version],
            ['payload' => $this->payload, 'scoring' => $this->scoring],
        );
    }

    // ---------------------------------------------------------------- relations

    /** @return HasMany<ActivityVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ActivityVersion::class)->orderBy('version');
    }

    /**
     * The version every new attempt must reference.
     *
     * The highest version number is by construction the current one, and
     * `ofMany` keeps this eager loadable.
     *
     * @return HasOne<ActivityVersion, $this>
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(ActivityVersion::class)->ofMany('version', 'max');
    }

    /** @return HasMany<ActivityPlacement, $this> */
    public function placements(): HasMany
    {
        return $this->hasMany(ActivityPlacement::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Hotel, $this> */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ----------------------------------------------------------------- scopes

    /**
     * @param  Builder<Activity>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ContentStatus::Published);
    }

    /**
     * @param  Builder<Activity>  $query
     */
    public function scopeOfType(Builder $query, ActivityType $type): void
    {
        $query->where('type', $type);
    }

    // ---------------------------------------------------------------- reading

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published;
    }

    public function isAutoScored(): bool
    {
        return $this->type->isAutoScored();
    }

    /**
     * Unlimited attempts is stored as 0 (PRAC-07).
     */
    public function allowsUnlimitedAttempts(): bool
    {
        return $this->attempts_allowed === 0;
    }

    /**
     * The items of the payload (spec 0003 B.9: every payload is {items: []}).
     *
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        return ActivityVersion::itemsOf($this->payload);
    }

    public function itemCount(): int
    {
        return count($this->items());
    }
}

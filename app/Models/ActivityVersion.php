<?php

namespace App\Models;

use Database\Factories\ActivityVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A frozen snapshot of an activity's payload and scoring (TEST-09, DATA-11,
 * spec 0003 B.5).
 *
 * Written once by Activity's model events and never edited: attempts point
 * here, so an answer always records exactly the question it answered. There
 * is no updated_at for that reason.
 *
 * @property int $id
 * @property int $activity_id
 * @property int $version
 * @property array<string, mixed> $payload
 * @property array<string, mixed>|null $scoring
 * @property Carbon|null $created_at
 */
#[Fillable(['activity_id', 'version', 'payload', 'scoring'])]
class ActivityVersion extends Model
{
    /** @use HasFactory<ActivityVersionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'payload' => 'array',
            'scoring' => 'array',
        ];
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * The items of this version's payload, in order (spec 0003 B.9).
     *
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        return self::itemsOf($this->payload);
    }

    /**
     * One item by its id, or null.
     *
     * @return array<string, mixed>|null
     */
    public function item(string $id): ?array
    {
        foreach ($this->items() as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Pull the item list out of a payload, tolerating a malformed one.
     *
     * @param  array<string, mixed>|null  $payload
     * @return list<array<string, mixed>>
     */
    public static function itemsOf(?array $payload): array
    {
        $items = $payload['items'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        /** @var list<array<string, mixed>> $list */
        $list = array_values(array_filter($items, 'is_array'));

        return $list;
    }
}

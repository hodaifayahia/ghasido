<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Who changed what, and when (SEC-06, ADM-03, spec 0002).
 *
 * Every mutation writes one of these. A change that reaches the database
 * without one is a defect (spec 0002, invariant 4), which is why the two
 * shapes below are settled here rather than reinvented at each call site.
 *
 * @property int $id
 * @property int|null $actor_id
 * @property string $action
 * @property string $auditable_type
 * @property int $auditable_id
 * @property array<string, mixed>|null $changes
 * @property string|null $ip
 * @property Carbon|null $created_at
 */
#[Fillable(['actor_id', 'action', 'auditable_type', 'auditable_id', 'changes', 'ip'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return MorphTo<Model, $this> */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Record a change to one model, taking the before and after from Eloquent
     * rather than rebuilding it by hand.
     *
     * Call this AFTER the attributes are set and BEFORE save(), so the dirty
     * attributes are still readable. The returned closure writes the row once
     * the save has happened.
     *
     * @param  array<string, mixed>  $extra  anything the diff cannot show, such as a rejection reason
     */
    public static function record(Model $model, string $action, array $extra = []): self
    {
        $changes = [];

        foreach ($model->getDirty() as $attribute => $after) {
            $changes[$attribute] = [
                'from' => $model->getOriginal($attribute),
                'to' => $after,
            ];
        }

        return self::write($model, $action, $changes === [] ? $extra : ['attributes' => $changes] + $extra);
    }

    /**
     * Record one save that touched several rows at once.
     *
     * A seat quota save changes many departments and must still be a single
     * audit row, so it carries its own shape instead of the attribute diff
     * above (spec 0002, Value sourcing):
     *
     *     {"quotas": {"<department_id>": {"from": N, "to": M}}}
     *
     * @param  array<int, array{from: int, to: int}>  $quotas  keyed by department id, changed rows only
     */
    public static function recordQuotaChange(Model $model, string $action, array $quotas): self
    {
        return self::write($model, $action, ['quotas' => $quotas]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private static function write(Model $model, string $action, array $changes): self
    {
        return self::create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'changes' => $changes === [] ? null : $changes,
            'ip' => Request::ip(),
        ]);
    }
}

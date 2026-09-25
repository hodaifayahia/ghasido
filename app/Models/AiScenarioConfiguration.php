<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A server-side authoring configuration for the AI scenario workspace
 * (RP-04, AIE-04, GEN-03).
 *
 * @property int $id
 * @property string $key
 * @property array<string, mixed> $value
 * @property int|null $updated_by
 */
#[Fillable(['key', 'value', 'updated_by'])]
class AiScenarioConfiguration extends Model
{
    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

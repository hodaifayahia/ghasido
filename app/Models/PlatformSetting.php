<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One small platform-wide setting the Super Admin edits in Settings →
 * Learning (ADM-02): the level-up threshold, the helper languages.
 *
 * @property int $id
 * @property string $key
 * @property array<string, mixed>|null $value
 * @property int|null $updated_by
 */
#[Fillable(['key', 'value', 'updated_by'])]
class PlatformSetting extends Model
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

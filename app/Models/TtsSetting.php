<?php

namespace App\Models;

use Database\Factories\TtsSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Safe, database-backed TTS controls. API credentials are intentionally not
 * modelled here and never cross the server boundary (API-02, SEC-03).
 *
 * @property int $id
 * @property string|null $voice
 * @property int|null $expressivity
 * @property int|null $updated_by
 */
#[Fillable(['voice', 'expressivity', 'updated_by'])]
class TtsSetting extends Model
{
    /** @use HasFactory<TtsSettingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['expressivity' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The single row of live voice-call controls (spec 0004). Credentials are
 * never modelled here (API-02, SEC-03); VoiceAgentSettings normalises the
 * stored values over its defaults.
 *
 * @property int $id
 * @property array<string, mixed>|null $values
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['values', 'updated_by'])]
class VoiceAgentSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['values' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

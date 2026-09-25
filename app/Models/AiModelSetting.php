<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The single row of AI model overrides the Super Admin edits on
 * Settings → AI models (API-04). Credentials and endpoints are never
 * modelled here (API-02, SEC-03); AiModelSettings normalises `values`.
 *
 * @property int $id
 * @property array<string, mixed>|null $values
 * @property array<string, mixed>|null $checks
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['values', 'checks', 'updated_by'])]
class AiModelSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['values' => 'array', 'checks' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

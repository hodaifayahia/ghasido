<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The editable copy for the public Guesvia landing page. The Super Admin is
 * the only role allowed to change it (ADM-02, SEC-01).
 *
 * @property int $id
 * @property array<string, mixed>|null $content
 * @property int|null $updated_by
 */
#[Fillable(['content', 'updated_by'])]
class LandingPageContent extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['content' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

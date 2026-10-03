<?php

namespace App\Models;

use App\Services\I18n\InterfaceLanguages;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An interface language added from Settings (client request 2026-10-03).
 * English and Arabic are built in and never stored here.
 *
 * @property int $id
 * @property string $code
 * @property string $name the English name, e.g. French
 * @property string $native_name its own name, e.g. Français
 * @property string $direction ltr | rtl
 * @property bool $enabled shown in the language menu
 * @property array<string, mixed>|null $messages English key → translation
 * @property string $status draft | generating | ready | failed
 * @property string|null $failed_reason
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'native_name', 'direction', 'enabled', 'messages', 'status', 'failed_reason', 'created_by'])]
class InterfaceLanguage extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'messages' => 'array',
        ];
    }

    protected static function booted(): void
    {
        $forget = fn (InterfaceLanguage $language) => InterfaceLanguages::forget($language->code);

        static::saved($forget);
        static::deleted($forget);
    }

    /** @return array<string, string> */
    public function translations(): array
    {
        return array_filter(
            $this->messages ?? [],
            fn ($value): bool => is_string($value) && trim($value) !== '',
        );
    }
}

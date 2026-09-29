<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide public subscription prices that are not tied to a hotel tier.
 */
#[Fillable(['individual_price_dzd', 'individual_price_usd', 'updated_by'])]
class SubscriptionCatalogSetting extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'individual_price_dzd' => 'integer',
            'individual_price_usd' => 'float',
        ];
    }
}

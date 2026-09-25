<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A payment instruction displayed to prospective hotel subscribers. */
#[Fillable(['name', 'recipient_name', 'account_reference', 'instructions', 'sort_order', 'is_active'])]
class SubscriptionPaymentMethod extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}

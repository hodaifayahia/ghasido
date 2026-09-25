<?php

namespace App\Models;

use App\Enums\ApiAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One recharge of a paid API account's credit (spec 0007, D4). The credit
 * is the sum of these rows; a negative row corrects a mistake. Write once:
 * the ledger is the audit trail of what the owner granted.
 *
 * @property int $id
 * @property ApiAccount $account
 * @property string $amount_usd
 * @property int $amount_tokens
 * @property string|null $note
 * @property int|null $owner_id
 * @property Carbon|null $created_at
 */
#[Fillable(['account', 'amount_usd', 'amount_tokens', 'note', 'owner_id'])]
class ApiCreditTopup extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account' => ApiAccount::class,
            'amount_usd' => 'decimal:4',
            'amount_tokens' => 'integer',
        ];
    }

    /** @return BelongsTo<Owner, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }
}

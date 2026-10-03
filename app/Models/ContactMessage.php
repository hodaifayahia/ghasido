<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One message sent from the public Contact Us page (client decision
 * 2026-09-26). Read by the Super Admin in Settings → Landing page.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $organisation
 * @property string|null $employees
 * @property string $message
 * @property string|null $ip
 * @property int|null $user_id the signed-in user who wrote from Help, if any
 * @property string|null $topic problem | extension | question | other
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ContactReply> $replies
 */
#[Fillable(['name', 'email', 'phone', 'organisation', 'employees', 'message', 'ip', 'read_at', 'user_id', 'topic'])]
class ContactMessage extends Model
{
    /** What a signed-in user writes about, from Help (client request 2026-10-02). */
    public const array TOPICS = ['problem', 'extension', 'question', 'other'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    /** @return HasMany<ContactReply, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(ContactReply::class)->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

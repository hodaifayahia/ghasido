<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The Super Admin's answer to a contact or support message (client request
 * 2026-10-03). It also reaches the writer's bell and email.
 *
 * @property int $id
 * @property int $contact_message_id
 * @property string $body
 * @property int|null $sent_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['contact_message_id', 'body', 'sent_by'])]
class ContactReply extends Model
{
    /** @return BelongsTo<ContactMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class, 'contact_message_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}

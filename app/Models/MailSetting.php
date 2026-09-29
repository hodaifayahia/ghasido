<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The single row of Settings → Email (client request 2026-09-29): the SMTP
 * mailbox every email is sent from. App\Services\Mail\MailSettings applies
 * it over config('mail.*'). The password is encrypted and hidden from
 * serialisation (SEC-03, like ApiAccountSetting's key).
 *
 * @property int $id
 * @property string $mailer
 * @property string|null $host
 * @property int|null $port
 * @property string $encryption
 * @property string|null $username
 * @property string|null $password
 * @property string|null $from_address
 * @property string|null $from_name
 * @property string|null $reply_to
 * @property bool $send_immediately
 * @property array<string, mixed>|null $last_test
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['mailer', 'host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name', 'reply_to', 'send_immediately', 'last_test', 'updated_by'])]
#[Hidden(['password'])]
class MailSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'password' => 'encrypted',
            'send_immediately' => 'boolean',
            'last_test' => 'array',
        ];
    }

    /**
     * The stored password, or null when none is stored or it no longer
     * decrypts (APP_KEY rotated).
     */
    public function secret(): ?string
    {
        try {
            $password = $this->getAttribute('password');
        } catch (DecryptException) {
            return null;
        }

        return is_string($password) && $password !== '' ? $password : null;
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

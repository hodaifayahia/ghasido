<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One email the platform tried to send (Settings → Email, "Recent emails").
 * `status` is sent (the mail server accepted it), failed (with the server's
 * answer, redacted) or logged (Log only mode: written to the log, not sent).
 *
 * @property int $id
 * @property string|null $to
 * @property string|null $subject
 * @property string|null $mailable
 * @property string|null $mailer
 * @property string $status
 * @property string|null $error
 * @property string|null $message_id
 * @property Carbon|null $created_at
 */
#[Fillable(['to', 'subject', 'mailable', 'mailer', 'status', 'error', 'message_id'])]
class MailLog extends Model
{
    public const string SENT = 'sent';

    public const string FAILED = 'failed';

    public const string LOGGED = 'logged';

    public const null UPDATED_AT = null;
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
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
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'phone', 'organisation', 'employees', 'message', 'ip', 'read_at'])]
class ContactMessage extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}

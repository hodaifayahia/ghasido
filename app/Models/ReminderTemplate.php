<?php

namespace App\Models;

use Database\Factories\ReminderTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A reusable reminder message (REM-04, spec 0003 B.7).
 *
 * The body holds the variables in self::VARIABLES, which
 * App\Services\Reminders\TemplateRenderer fills per recipient at send time.
 * The rendered text is copied onto each Reminder row, so a later edit here
 * never rewrites what somebody was actually sent.
 *
 * @property int $id
 * @property string $name
 * @property string $subject
 * @property string $body
 * @property string|null $audience_label
 * @property string|null $trigger_label
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'subject', 'body', 'audience_label', 'trigger_label', 'is_active'])]
class ReminderTemplate extends Model
{
    /** @use HasFactory<ReminderTemplateFactory> */
    use HasFactory;

    /**
     * The placeholders a template body or subject may use, without braces.
     *
     * @var list<string>
     */
    public const VARIABLES = ['name', 'hotel', 'department', 'progress', 'days_remaining', 'login_url'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Reminder, $this> */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'template_id');
    }

    /** @return HasMany<AutomationRule, $this> */
    public function automationRules(): HasMany
    {
        return $this->hasMany(AutomationRule::class, 'template_id');
    }

    /**
     * Templates the send dialog and the runner may use.
     *
     * @param  Builder<ReminderTemplate>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

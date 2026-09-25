<?php

namespace App\Models;

use App\Enums\AutomationTrigger;
use Database\Factories\AutomationRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An automatic reminder rule (REM-03, spec 0003 B.7).
 *
 * App\Services\Reminders\AutomationRunner evaluates every active rule once a
 * day: it picks recipients from the trigger, narrows them to the audience,
 * skips anybody this rule reached inside the repeat window, and sends through
 * ReminderService so consent is checked in exactly one place (REM-05).
 *
 * @property int $id
 * @property string $name
 * @property AutomationTrigger $trigger
 * @property int|null $days
 * @property int $template_id
 * @property array<string, mixed>|null $audience `{hotel_ids: [], department_ids: []}`
 * @property string|null $audience_label
 * @property bool $is_active
 * @property Carbon|null $last_run_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ReminderTemplate|null $template
 */
#[Fillable(['name', 'trigger', 'days', 'template_id', 'audience', 'audience_label', 'is_active', 'last_run_at'])]
class AutomationRule extends Model
{
    /** @use HasFactory<AutomationRuleFactory> */
    use HasFactory;

    /**
     * How many days must pass before the same rule may reach the same
     * employee again, when the rule does not say.
     */
    public const DEFAULT_REPEAT_DAYS = 7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger' => AutomationTrigger::class,
            'days' => 'integer',
            'audience' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ReminderTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ReminderTemplate::class, 'template_id');
    }

    /** @return HasMany<Reminder, $this> */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    /**
     * @param  Builder<AutomationRule>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Hotels this rule is limited to; empty means every hotel.
     *
     * @return list<int>
     */
    public function hotelIds(): array
    {
        return $this->audienceIds('hotel_ids');
    }

    /**
     * Departments this rule is limited to; empty means every department.
     *
     * @return list<int>
     */
    public function departmentIds(): array
    {
        return $this->audienceIds('department_ids');
    }

    /**
     * The inactivity window for `inactive_days`, falling back to the
     * configured platform default (spec 0003, Part C).
     */
    public function inactivityDays(): int
    {
        return $this->days ?? (int) config('guesvia.reminders.inactive_days', 5);
    }

    /**
     * How long an employee is left alone after this rule reached them.
     */
    public function repeatWindowDays(): int
    {
        return $this->days ?? self::DEFAULT_REPEAT_DAYS;
    }

    /**
     * @return list<int>
     */
    private function audienceIds(string $key): array
    {
        $ids = $this->audience[$key] ?? [];

        if (! is_array($ids)) {
            return [];
        }

        $clean = [];

        foreach ($ids as $id) {
            if (is_numeric($id)) {
                $clean[] = (int) $id;
            }
        }

        return $clean;
    }
}

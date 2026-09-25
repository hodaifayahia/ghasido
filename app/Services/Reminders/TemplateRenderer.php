<?php

namespace App\Services\Reminders;

use App\Models\User;

/**
 * Fills a reminder template's placeholders for one recipient (REM-04).
 *
 * The six variables are fixed in ReminderTemplate::VARIABLES. A placeholder
 * the renderer does not know is left exactly as written, so a typo in a
 * template is visible in the preview instead of vanishing.
 *
 * Progress and the post-test come from the learner lanes: `progressPercent()`
 * is called only when User has it, and reads as 0 until then.
 */
final class TemplateRenderer
{
    /**
     * Matches {{name}} and {{ name }} alike; the variable name is group 1.
     */
    private const PLACEHOLDER = '/\{\{\s*([a-z_]+)\s*\}\}/i';

    public function render(string $text, User $user): string
    {
        $values = $this->values($user);

        $rendered = preg_replace_callback(
            self::PLACEHOLDER,
            static function (array $match) use ($values): string {
                $key = strtolower($match[1]);

                return array_key_exists($key, $values) ? $values[$key] : $match[0];
            },
            $text,
        );

        return $rendered ?? $text;
    }

    /**
     * Every variable's value for this recipient, keyed without braces.
     *
     * @return array<string, string>
     */
    public function values(User $user): array
    {
        $hotel = $user->hotel;
        $department = $user->department;
        $daysRemaining = $hotel?->daysRemaining();

        return [
            'name' => $user->name,
            'hotel' => $hotel !== null ? $hotel->name : '',
            'department' => $department !== null ? $department->name : '',
            'progress' => (string) $this->progressPercent($user),
            'days_remaining' => $daysRemaining === null ? '' : (string) max($daysRemaining, 0),
            'login_url' => route('login'),
        ];
    }

    /**
     * The employee's overall progress as a whole percentage, from the
     * learner lane's User::progressPercent() (DATA-07).
     */
    private function progressPercent(User $user): int
    {
        return max(0, min(100, $user->progressPercent()));
    }
}

<?php

namespace App\Http\Requests\Admin\Messages;

use App\Enums\ReminderChannel;
use App\Models\Reminder;
use App\Models\ReminderTemplate;
use App\Services\Reminders\RecipientQuery;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

/**
 * Send Reminder (REM-01, REM-02, REM-05; spec 0003 Part D).
 *
 * Recipients are either an explicit list of ids or "everyone matching the
 * current filters"; ReminderBatchService resolves both on the server and
 * refuses any id outside the actor's hotel with a 403 (REM-07).
 */
class SendReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('send', Reminder::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'template_id' => [
                'required',
                'integer',
                Rule::exists(ReminderTemplate::class, 'id')->where('is_active', true),
            ],
            'channel' => ['required', Rule::enum(ReminderChannel::class)],
            'select_all' => ['sometimes', 'boolean'],
            'recipients' => ['required_unless:select_all,1,true', 'array'],
            'recipients.*' => ['integer', 'distinct'],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
            'hotel' => ['nullable', 'string', 'max:40'],
            'department' => ['nullable', 'string', 'max:40'],
            'consent' => ['nullable', 'string', 'max:40'],
            'activity' => ['nullable', 'string', 'max:40'],
            'search' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipients.required_unless' => __('Choose at least one employee to remind.'),
            'template_id.exists' => __('Choose an active reminder template.'),
            'scheduled_for.after' => __('A scheduled reminder must be in the future.'),
        ];
    }

    public function template(): ReminderTemplate
    {
        return ReminderTemplate::query()->findOrFail((int) $this->validated('template_id'));
    }

    public function channel(): ReminderChannel
    {
        return ReminderChannel::from((string) $this->validated('channel'));
    }

    public function scheduledFor(): ?CarbonInterface
    {
        $value = $this->validated('scheduled_for');

        return is_string($value) && $value !== '' ? Date::parse($value) : null;
    }

    public function selectAll(): bool
    {
        return $this->boolean('select_all');
    }

    /**
     * @return list<int>
     */
    public function recipientIds(): array
    {
        $ids = $this->validated('recipients');

        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_map('intval', $ids));
    }

    /**
     * The screen filters behind "select all matching".
     *
     * @return array{hotel: string, department: string, consent: string, activity: string, search: string}
     */
    public function filters(): array
    {
        return [
            'hotel' => $this->string('hotel', RecipientQuery::ALL_HOTELS)->toString(),
            'department' => $this->string('department', RecipientQuery::ALL_DEPARTMENTS)->toString(),
            'consent' => $this->string('consent', RecipientQuery::ALL_CONSENT)->toString(),
            'activity' => $this->string('activity', RecipientQuery::ALL_ACTIVITY)->toString(),
            'search' => trim($this->string('search', '')->toString()),
        ];
    }
}

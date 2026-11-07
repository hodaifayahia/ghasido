<?php

namespace App\Http\Requests\Learn;

use App\Models\Reminder;
use App\Models\ReminderReply;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A learner's answer to a message (client request 2026-11-07).
 *
 * Only the message's own recipient may answer, and only a message sent
 * with replies allowed; anything else is a 403 (ROLE-02, SEC-01). This is
 * decided here rather than in a policy because Gate::before lets the Super
 * Admin through every ability, and nobody may answer for somebody else.
 *
 * The text is required unless a rating is given; a rating is accepted only
 * when the message asked for one.
 */
class ReplyToReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reminder = $this->reminder();
        $user = $this->user('web');

        return $reminder !== null
            && $user !== null
            && $reminder->user_id === $user->id
            && $reminder->allow_replies;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $asksRating = $this->reminder()->ask_rating ?? false;

        return [
            'body' => [
                $asksRating ? 'required_without:rating' : 'required',
                'nullable',
                'string',
                'max:'.ReminderReply::MAX_BODY,
            ],
            'rating' => $asksRating
                ? ['nullable', 'integer', 'between:1,5']
                : ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => __('Write your reply.'),
            'body.required_without' => __('Write your reply or choose a rating.'),
            'body.max' => __('Your reply may be at most :max characters.', ['max' => ReminderReply::MAX_BODY]),
        ];
    }

    public function reminder(): ?Reminder
    {
        $reminder = $this->route('reminder');

        return $reminder instanceof Reminder ? $reminder : null;
    }

    /** The text exactly as written; null when the learner only rated. */
    public function body(): ?string
    {
        $body = $this->validated('body');

        return is_string($body) && trim($body) !== '' ? $body : null;
    }

    public function rating(): ?int
    {
        $rating = $this->validated('rating');

        return $rating === null || $rating === '' ? null : (int) $rating;
    }
}

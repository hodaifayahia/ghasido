<?php

namespace App\Services\Reminders;

use App\Models\Reminder;
use App\Models\ReminderReply;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Writes a learner's answer to a message that allowed replies, and marks
 * replies read for the team (client request 2026-11-07).
 *
 * One answer per reminder row: a second submit edits it. An edit counts
 * as new for the team again, so a changed opinion is not missed.
 */
class ReminderReplyService
{
    /**
     * @return array{reply: ReminderReply, created: bool}
     */
    public function save(Reminder $reminder, User $learner, ?string $body, ?int $rating): array
    {
        return DB::transaction(function () use ($reminder, $learner, $body, $rating): array {
            $reply = ReminderReply::query()->firstOrNew(['reminder_id' => $reminder->id]);
            $created = ! $reply->exists;

            $reply->fill([
                'user_id' => $learner->id,
                'body' => $body,
                'rating' => $reminder->ask_rating ? $rating : null,
                'read_at' => null,
            ])->save();

            // Answering a message is reading it.
            $reminder->markRead();

            return ['reply' => $reply, 'created' => $created];
        });
    }

    /**
     * Mark the replies to one message read, as far as this admin can see
     * them: a manager only touches their own hotel's replies (ROLE-02).
     * Returns how many changed.
     */
    public function markBatchRead(User $actor, string $batch): int
    {
        return ReminderReply::query()
            ->visibleTo($actor)
            ->unread()
            ->whereHas('reminder', fn ($reminder) => $reminder->where('batch', $batch))
            ->update(['read_at' => now()]);
    }
}

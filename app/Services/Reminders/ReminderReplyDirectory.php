<?php

namespace App\Services\Reminders;

use App\Enums\Role;
use App\Models\Reminder;
use App\Models\ReminderReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * The read side of replies to messages (client request 2026-11-07): the
 * unread badge, the figures beside each sent message in the log, and the
 * Replies panel that lists who answered which message.
 *
 * A message is one Send: every reminder row sharing a `batch`. A manager
 * sees only the rows and replies of their own hotel's employees, so a
 * message the Super Admin sent to several hotels shows a manager their
 * part of it alone (REM-07, ROLE-02).
 */
class ReminderReplyDirectory
{
    /** How many messages the Replies panel lists, newest first. */
    public const THREADS = 30;

    public function unreadCount(User $actor): int
    {
        return ReminderReply::query()->visibleTo($actor)->unread()->count();
    }

    /**
     * Recipients and replies per message, for the log rows on screen.
     *
     * @param  list<string>  $batches
     * @return array<string, array{recipients: int, replies: int}>
     */
    public function figures(User $actor, array $batches): array
    {
        if ($batches === []) {
            return [];
        }

        $rows = $this->reminders($actor)
            ->whereIn('reminders.batch', $batches)
            ->leftJoin('reminder_replies', 'reminder_replies.reminder_id', '=', 'reminders.id')
            ->groupBy('reminders.batch')
            ->selectRaw('reminders.batch as batch, COUNT(reminders.id) as recipients, COUNT(reminder_replies.id) as replies')
            ->toBase()
            ->get();

        $figures = [];

        foreach ($rows as $row) {
            $figures[(string) $row->batch] = [
                'recipients' => (int) $row->recipients,
                'replies' => (int) $row->replies,
            ];
        }

        return $figures;
    }

    /**
     * Of these reminder rows, the ones the recipient answered.
     *
     * @param  list<int>  $reminderIds
     * @return list<int>
     */
    public function answered(array $reminderIds): array
    {
        if ($reminderIds === []) {
            return [];
        }

        return array_values(array_map(
            intval(...),
            ReminderReply::query()->whereIn('reminder_id', $reminderIds)->pluck('reminder_id')->all(),
        ));
    }

    /**
     * The messages that allowed replies, newest first, each with its
     * replies. `$focus` (a batch, from a bell click or a log row) is always
     * listed, however old.
     *
     * @return list<array<string, mixed>>
     */
    public function threads(User $actor, ?string $focus = null): array
    {
        $summary = fn (Builder $query) => $query
            ->groupBy('reminders.batch')
            ->selectRaw('reminders.batch as batch, MIN(reminders.id) as first_id, COUNT(reminders.id) as recipients')
            ->toBase();

        $rows = $summary($this->reminders($actor))
            ->orderByDesc('first_id')
            ->limit(self::THREADS)
            ->get();

        if ($focus !== null && $focus !== '' && ! $rows->contains(fn (object $row): bool => $row->batch === $focus)) {
            $rows = $rows->concat($summary($this->reminders($actor)->where('reminders.batch', $focus))->get());
        }

        if ($rows->isEmpty()) {
            return [];
        }

        /** @var list<string> $batches */
        $batches = $rows->map(fn (object $row): string => (string) $row->batch)->values()->all();

        $first = Reminder::query()
            ->with('template')
            ->whereIn('id', $rows->map(fn (object $row): int => (int) $row->first_id)->all())
            ->get()
            ->keyBy('id');

        $replies = ReminderReply::query()
            ->visibleTo($actor)
            ->whereHas('reminder', fn (Builder $reminder) => $reminder->whereIn('batch', $batches))
            ->with(['reminder:id,batch', 'user.hotel', 'user.department'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (ReminderReply $reply): string => (string) $reply->reminder?->batch);

        $threads = [];

        foreach ($rows as $row) {
            $batch = (string) $row->batch;
            /** @var Reminder|null $message */
            $message = $first->get((int) $row->first_id);

            if ($message === null) {
                continue;
            }

            $answers = $replies->get($batch, collect());
            $ratings = $answers->pluck('rating')->filter(fn (mixed $rating): bool => $rating !== null);

            $threads[] = [
                'batch' => $batch,
                'title' => $message->template->name ?? $message->subject,
                'subject' => $message->subject,
                'channel' => $message->channel->value,
                'sentAt' => MessagesDirectory::formatDateTime($message->noticedAt()),
                'askRating' => $message->ask_rating,
                'recipients' => (int) $row->recipients,
                'replyCount' => $answers->count(),
                'unread' => $answers->whereNull('read_at')->count(),
                'averageRating' => $ratings->isEmpty() ? null : round((float) $ratings->avg(), 1),
                'replies' => $answers
                    ->map(fn (ReminderReply $reply): array => $this->reply($reply))
                    ->values()
                    ->all(),
            ];
        }

        return $threads;
    }

    /**
     * The unread replies for the topbar bell, newest first.
     *
     * @return Collection<int, ReminderReply>
     */
    public function latestUnread(User $actor, int $limit = 10)
    {
        return ReminderReply::query()
            ->visibleTo($actor)
            ->unread()
            ->with(['user', 'reminder.template'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function reply(ReminderReply $reply): array
    {
        $user = $reply->user;
        $created = $reply->created_at;
        $updated = $reply->updated_at ?? $created;

        return [
            'id' => $reply->id,
            'name' => $user->name ?? __('Unknown employee'),
            'hotel' => $user?->hotel->name ?? '—',
            'department' => $user?->department->name ?? '—',
            'body' => $reply->body,
            'rating' => $reply->rating,
            'repliedAt' => MessagesDirectory::formatDateTime($updated),
            'edited' => $created instanceof Carbon && $updated instanceof Carbon && $updated->greaterThan($created->copy()->addSecond()),
            'unread' => $reply->read_at === null,
        ];
    }

    /**
     * Reminder rows of messages that allowed replies, as far as this actor
     * may read the log (REM-06, REM-07).
     *
     * @return Builder<Reminder>
     */
    private function reminders(User $actor): Builder
    {
        $query = Reminder::query()
            ->where('reminders.allow_replies', true)
            ->whereNotNull('reminders.batch');

        if (! $actor->hasRole(Role::SuperAdmin->value)) {
            $hotelId = $actor->hotel_id ?? 0;

            $query->whereHas('user', fn (Builder $user) => $user->where('users.hotel_id', $hotelId));
        }

        return $query;
    }
}

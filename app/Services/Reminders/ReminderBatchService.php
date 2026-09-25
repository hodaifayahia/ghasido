<?php

namespace App\Services\Reminders;

use App\Enums\AccountStatus;
use App\Enums\ReminderChannel;
use App\Enums\Role;
use App\Models\ReminderTemplate;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * A manual send from the Messages screen (REM-01, REM-02, REM-07; spec 0003
 * Part D): resolve who was picked, refuse anybody outside the actor's hotel,
 * hand the rest to ReminderService.
 *
 * Two ways to pick recipients, both resolved here and never trusted from the
 * form as-is: an explicit list of ids, or "everyone matching the current
 * filters", which re-runs RecipientQuery on the server so the count the
 * dialog showed is the count that is sent.
 */
class ReminderBatchService
{
    public function __construct(private readonly ReminderService $reminders) {}

    /**
     * @param  list<int>  $ids  explicit recipients; ignored when $selectAll
     * @param  array{hotel?: string, department?: string, consent?: string, activity?: string, search?: string}  $filters  the screen filters behind "select all"
     * @return array{total: int, delivered: int, scheduled: int, blocked: int, skipped: int}
     *
     * @throws AuthorizationException when an id is not one of the actor's employees
     */
    public function send(
        User $actor,
        array $ids,
        bool $selectAll,
        array $filters,
        ReminderTemplate $template,
        ReminderChannel $channel,
        ?CarbonInterface $scheduledFor,
    ): array {
        $recipients = $selectAll
            ? $this->everyoneMatching($actor, $filters)
            : $this->exactly($actor, $ids);

        // A deactivated account gets nothing (AUTH-08); it is counted so the
        // toast can say so rather than dropping it silently.
        [$active, $inactive] = $recipients->partition(
            fn (User $user): bool => $user->status === AccountStatus::Active,
        );

        $sent = $this->reminders->send($active->values(), $template, $channel, $actor, null, $scheduledFor);

        return $this->reminders->summarise($sent) + ['skipped' => $inactive->count()];
    }

    /**
     * @param  array{hotel?: string, department?: string, consent?: string, activity?: string, search?: string}  $filters
     * @return EloquentCollection<int, User>
     */
    private function everyoneMatching(User $actor, array $filters): EloquentCollection
    {
        // forActor() already pins a manager to their hotel; a hotel filter
        // naming another one must not turn the batch into nobody, nor widen
        // it (REM-07).
        if (! $actor->hasRole(Role::SuperAdmin->value)) {
            $filters['hotel'] = RecipientQuery::ALL_HOTELS;
        }

        return RecipientQuery::apply(RecipientQuery::forActor($actor), $filters)->get();
    }

    /**
     * The listed employees, all of whom must be the actor's to address. One
     * id from another hotel refuses the whole batch with a 403 rather than
     * silently sending to the rest (ROLE-02, SEC-01).
     *
     * @param  list<int>  $ids
     * @return Collection<int, User>
     */
    private function exactly(User $actor, array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return new Collection;
        }

        $recipients = RecipientQuery::forActor($actor)->whereIn('users.id', $ids)->get();

        if ($recipients->count() !== count($ids)) {
            throw new AuthorizationException(__('One or more recipients are not employees you can message.'));
        }

        return $recipients;
    }
}

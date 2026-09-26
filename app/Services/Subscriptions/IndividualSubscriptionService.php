<?php

namespace App\Services\Subscriptions;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\IndividualSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Every write to an individual subscriber: a learner who uses GHASIDO with
 * no hotel, on their own configuration (user request 2026-09-25).
 *
 * The learner is an ordinary `employee` user with hotel_id NULL, so lessons,
 * tests, the phrasebook and AI practice all work unchanged; content is the
 * shared catalogue of their department. Their IndividualSubscription row
 * replaces the hotel's contract and plan. One transaction and one audit row
 * per change (SEC-06); nothing is ever deleted (DATA-10).
 *
 * @phpstan-type IndividualData array{
 *     name: string,
 *     username: string,
 *     email: string|null,
 *     password?: string,
 *     department_ids: list<int>,
 *     status: string,
 *     ai_points_allocated: int,
 *     starts_on: string|null,
 *     ends_on: string|null,
 *     ai_enabled: bool,
 *     voice_enabled: bool,
 *     daily_ai_turns: int|null,
 *     ai_action_points: int,
 *     voice_points_per_10_minutes: int,
 *     price_dzd: int|null,
 *     price_usd: float|null,
 *     payment_reference: string|null,
 *     notes: string|null,
 * }
 */
class IndividualSubscriptionService
{
    /**
     * @param  IndividualData  $data
     */
    public function create(array $data, User $creator): User
    {
        return DB::transaction(function () use ($data, $creator): User {
            $user = new User;
            $user->fill([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'] ?? '',
                'hotel_id' => null,
                // The first department is their main one.
                'department_id' => $data['department_ids'][0],
                'status' => AccountStatus::from($data['status']),
                'participant_code' => User::generateParticipantCode(),
                'created_by' => $creator->id,
                'ai_points_allocated' => $data['ai_points_allocated'],
            ]);
            $user->save();
            $user->setRole(Role::Employee);

            $subscription = new IndividualSubscription;
            $subscription->fill([
                ...$this->subscriptionFields($data),
                'user_id' => $user->id,
                'created_by' => $creator->id,
            ])->save();
            $this->syncDepartments($subscription, $data['department_ids']);

            AuditLog::record($user, 'individual.created', [
                'created' => [
                    ...$user->only(['name', 'username', 'email', 'department_id', 'status', 'ai_points_allocated']),
                    ...$this->subscriptionFields($data),
                    'department_ids' => $data['department_ids'],
                ],
            ]);

            return $user;
        });
    }

    /**
     * @param  IndividualData  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user->fill([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'department_id' => $data['department_ids'][0],
                'status' => AccountStatus::from($data['status']),
                'ai_points_allocated' => $data['ai_points_allocated'],
            ]);

            if (isset($data['password']) && $data['password'] !== '') {
                $user->password = $data['password'];
            }

            $subscription = $user->individualSubscription ?? new IndividualSubscription(['user_id' => $user->id]);
            $subscription->fill($this->subscriptionFields($data));

            $changes = [];
            foreach ([...$user->getDirty(), ...$subscription->getDirty()] as $attribute => $value) {
                if ($attribute === 'password') {
                    $changes['password'] = ['from' => '••••', 'to' => '••••'];

                    continue;
                }

                $changes[$attribute] = [
                    'from' => $user->isDirty($attribute) ? $user->getOriginal($attribute) : $subscription->getOriginal($attribute),
                    'to' => $value,
                ];
            }

            $user->save();
            $subscription->save();

            $before = $subscription->departmentIds();
            $this->syncDepartments($subscription, $data['department_ids']);

            if ($before !== $data['department_ids']) {
                $changes['department_ids'] = ['from' => $before, 'to' => $data['department_ids']];
            }

            if ($changes !== []) {
                AuditLog::record($user, 'individual.updated', ['attributes' => $changes]);
            }

            return $user->refresh();
        });
    }

    /** Switch the account on or off; the data stays either way (AUTH-08). */
    public function toggle(User $user): User
    {
        return DB::transaction(function () use ($user): User {
            $user->status = $user->status === AccountStatus::Active ? AccountStatus::Inactive : AccountStatus::Active;
            AuditLog::record($user, $user->status === AccountStatus::Active ? 'individual.activated' : 'individual.deactivated');
            $user->save();

            return $user;
        });
    }

    /**
     * The subscriber's departments, in the order chosen (main one first).
     *
     * @param  list<int>  $ids
     */
    private function syncDepartments(IndividualSubscription $subscription, array $ids): void
    {
        $pivot = [];
        foreach (array_values(array_unique($ids)) as $index => $id) {
            $pivot[$id] = ['position' => $index + 1];
        }

        $subscription->departments()->sync($pivot);
    }

    /**
     * @param  IndividualData  $data
     * @return array<string, mixed>
     */
    private function subscriptionFields(array $data): array
    {
        return [
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'ai_enabled' => $data['ai_enabled'],
            'voice_enabled' => $data['voice_enabled'],
            'daily_ai_turns' => $data['daily_ai_turns'],
            'ai_action_points' => $data['ai_action_points'],
            'voice_points_per_10_minutes' => $data['voice_points_per_10_minutes'],
            'price_dzd' => $data['price_dzd'],
            'price_usd' => $data['price_usd'],
            'payment_reference' => $data['payment_reference'],
            'notes' => $data['notes'],
        ];
    }
}

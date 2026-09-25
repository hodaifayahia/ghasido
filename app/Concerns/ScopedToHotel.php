<?php

namespace App\Concerns;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Keeps one hotel from reading another hotel's rows (ROLE-02, SEC-01).
 *
 * This is a safety net, not the authorization. A forgotten where clause in
 * some future feature cannot leak across tenants with this in place, but the
 * policy is still what decides whether an action is allowed (spec 0002,
 * Security model). Never rely on the scope alone to refuse something.
 *
 * A Super Admin passes through untouched, and so does anything running outside
 * a request (a seeder, a console command, a queued job), because there is no
 * signed in user to scope to.
 *
 * The model using this trait must have a `hotel_id` column, or be the Hotel
 * itself, which scopes on its own `id`.
 */
trait ScopedToHotel
{
    public static function bootScopedToHotel(): void
    {
        static::addGlobalScope('hotel', function (Builder $builder): void {
            /** @var User|null $user */
            $user = Auth::user();

            if ($user === null) {
                return;
            }

            if ($user->hasRole(Role::SuperAdmin->value)) {
                return;
            }

            /** @var Model $model */
            $model = $builder->getModel();

            $column = $model->getTable() === 'hotels'
                ? $model->getQualifiedKeyName()
                : $model->qualifyColumn('hotel_id');

            // A user with no hotel (a manager who has not been attached to one
            // yet) sees nothing rather than everything.
            $builder->where($column, $user->hotel_id);
        });
    }
}

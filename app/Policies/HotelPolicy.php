<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Hotel;
use App\Models\User;

/**
 * Who may do what to a hotel (ROLE-01, ROLE-02, SEC-01, spec 0002).
 *
 * Two questions every method answers, in this order: does this user hold the
 * capability at all, and is this particular hotel theirs to touch. The global
 * scope in App\Concerns\ScopedToHotel is only a safety net; these methods are
 * the authorization (spec 0002, Security model).
 *
 * The Super Admin never reaches any of this: Gate::before in
 * AppServiceProvider returns true for them first (spec 0001, AC-2).
 */
class HotelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::HotelsView->value);
    }

    public function view(User $user, Hotel $hotel): bool
    {
        return $user->can(Permission::HotelsView->value)
            && $this->belongsTo($user, $hotel);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::HotelsManage->value);
    }

    public function update(User $user, Hotel $hotel): bool
    {
        return $user->can(Permission::HotelsManage->value)
            && $this->belongsTo($user, $hotel)
            && ! $hotel->archived_at;
    }

    /**
     * Approving or rejecting is its own capability, held only by the platform
     * owner: a hotel cannot approve itself.
     *
     * Whether the hotel is actually pending is a state question, not a
     * permission one: HotelService answers it with a 409 so a stale button
     * is told apart from a boundary (spec 0002, API surface).
     */
    public function approve(User $user, Hotel $hotel): bool
    {
        return $user->can(Permission::HotelsApprove->value)
            && $this->belongsTo($user, $hotel);
    }

    public function archive(User $user, Hotel $hotel): bool
    {
        return $user->can(Permission::HotelsManage->value)
            && $this->belongsTo($user, $hotel);
    }

    /**
     * Safe delete (owner decision 2026-09-27): who may archive may delete;
     * the delete itself is refused while anything depends on the hotel.
     */
    public function delete(User $user, Hotel $hotel): bool
    {
        return $this->archive($user, $hotel);
    }

    public function manageContract(User $user, Hotel $hotel): bool
    {
        return $this->update($user, $hotel);
    }

    public function manageSeats(User $user, Hotel $hotel): bool
    {
        return $this->update($user, $hotel);
    }

    /**
     * Is this hotel the user's own?
     *
     * A user with no hotel matches nothing, which is the safe answer: it means
     * a manager who has not been attached to a hotel yet is refused rather
     * than handed the first row that happens to have a null hotel_id.
     */
    private function belongsTo(User $user, Hotel $hotel): bool
    {
        return $user->hotel_id !== null && $user->hotel_id === $hotel->id;
    }
}

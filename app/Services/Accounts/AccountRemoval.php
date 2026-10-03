<?php

namespace App\Services\Accounts;

use App\Enums\AccountStatus;
use App\Enums\HotelAccessState;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "Delete" an employee or a hotel (client request 2026-10-02: archive and
 * deactivate keep everything on screen, and the email or username cannot
 * be used again).
 *
 * Deleting never erases learning data: answers, recordings and progress
 * are the research record (DATA-10). The account is anonymised instead:
 * its name, username, email and phone go, so they are free for another
 * account; it can no longer sign in, and it leaves every list and seat
 * count. Its answers stay in the reports under its participant code.
 */
final class AccountRemoval
{
    public function removeUser(User $user, User $actor): void
    {
        if ($user->removed_at !== null) {
            return;
        }

        DB::transaction(function () use ($user, $actor): void {
            // Who did it and which account, never the personal details.
            AuditLog::record($user, 'user.removed', ['by' => $actor->id]);

            $user->forceFill([
                'name' => __('Deleted account'),
                'username' => 'deleted-'.$user->id.'-'.Str::lower(Str::random(6)),
                'email' => null,
                'phone' => null,
                'status' => AccountStatus::Inactive,
                'password' => Str::random(40),
                'remember_token' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'email_consent_at' => null,
                'removed_at' => Date::now(),
            ])->save();

            $user->passkeys()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
    }

    /**
     * An archived hotel leaves every list; its accounts are deleted as above.
     *
     * @throws ValidationException when the hotel is not archived first
     */
    public function removeHotel(Hotel $hotel, User $actor): void
    {
        if ($hotel->access_state !== HotelAccessState::Archived) {
            throw ValidationException::withMessages([
                'hotel' => __('Archive the hotel first; only an archived hotel can be deleted.'),
            ]);
        }

        DB::transaction(function () use ($hotel, $actor): void {
            User::query()
                ->where('hotel_id', $hotel->id)
                ->whereNull('removed_at')
                ->each(fn (User $user) => $this->removeUser($user, $actor));

            AuditLog::record($hotel, 'hotel.removed', ['by' => $actor->id]);
            $hotel->forceFill(['removed_at' => Date::now()])->save();
        });
    }
}

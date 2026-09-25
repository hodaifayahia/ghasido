<?php

namespace App\Services\Hotels;

use App\Enums\AccountStatus;
use App\Enums\HotelAccessState;
use App\Enums\Role;
use App\Mail\HotelApprovedMail;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Every write to a hotel (spec 0002, AC-12 to AC-17, AC-20).
 *
 * The controller validates through a form request and hands the clean data
 * here. Each method changes the row and writes exactly one audit row inside
 * one transaction, so a change cannot reach the database without its trace
 * (spec 0002, invariant 4; SEC-06, ADM-03).
 *
 * State guards throw HotelStateConflict (409). The policy has already said the
 * actor MAY do this; a conflict only says the hotel is not in a state that can
 * take it.
 */
class HotelService
{
    /**
     * A guest hotel's request: create the pending hotel plus its first manager
     * account, inactive until approval.
     *
     * @param  array{name: string, city: string, manager_name: string, manager_email: string, subscription_plan_id?: int}  $hotelData
     * @param  array{name: string, username: string, email: string, password: string}  $managerData
     */
    public function requestAccess(array $hotelData, array $managerData): Hotel
    {
        return DB::transaction(function () use ($hotelData, $managerData): Hotel {
            $hotel = new Hotel;
            $hotel->fill([
                ...$hotelData,
                'subscription_plan_id' => $hotelData['subscription_plan_id'] ?? $this->defaultPlanId(),
                'contract_starts_on' => null,
                'contract_ends_on' => null,
            ]);
            $hotel->slug = $this->uniqueSlug($hotelData['name']);
            $hotel->access_state = HotelAccessState::Pending;
            $hotel->save();

            AuditLog::record($hotel, 'hotel.requested', [
                'created' => $hotel->only(['name', 'city', 'manager_name', 'manager_email']),
            ]);

            $manager = new User;
            $manager->fill([
                'name' => $managerData['name'],
                'username' => $managerData['username'],
                'email' => $managerData['email'],
                'password' => $managerData['password'],
                'hotel_id' => $hotel->id,
                'status' => AccountStatus::Inactive,
            ]);
            $manager->save();
            $manager->setRole(Role::Manager);

            AuditLog::record($manager, 'manager.requested', [
                'hotel_id' => $hotel->id,
                'hotel_name' => $hotel->name,
            ]);

            return $hotel;
        });
    }

    /**
     * Create a hotel. It always lands pending, whatever the form said
     * (spec 0002, AC-12).
     *
     * @param  array{name: string, city: string, manager_name: string, manager_email: string, contract_starts_on: string, contract_ends_on: string}  $data
     */
    public function create(array $data): Hotel
    {
        return DB::transaction(function () use ($data): Hotel {
            $hotel = new Hotel;
            $hotel->fill($data);
            $hotel->subscription_plan_id ??= $this->defaultPlanId();
            $hotel->slug = $this->uniqueSlug($data['name']);
            $hotel->access_state = HotelAccessState::Pending;
            $hotel->save();

            AuditLog::record($hotel, 'hotel.created', [
                'created' => $hotel->only(['name', 'city', 'manager_name', 'manager_email', 'contract_starts_on', 'contract_ends_on']),
            ]);

            return $hotel;
        });
    }

    /**
     * @param  array{name: string, city: string, manager_name: string, manager_email: string, contract_starts_on: string, contract_ends_on: string}  $data
     */
    public function update(Hotel $hotel, array $data): Hotel
    {
        return $this->change($hotel, 'hotel.updated', function (Hotel $hotel) use ($data): void {
            $hotel->fill($data);
        });
    }

    /**
     * Approve a pending hotel: start the clock today and move the end date by
     * the same offset, so the agreed duration survives a late approval
     * (spec 0002, AC-13).
     */
    public function approve(Hotel $hotel, User $approver): Hotel
    {
        $this->expect($hotel, HotelAccessState::Pending, 'approve');

        return DB::transaction(function () use ($hotel, $approver): Hotel {
            $today = Date::today();

            if ($hotel->contract_starts_on !== null && $hotel->contract_ends_on !== null) {
                $offset = (int) $hotel->contract_starts_on->startOfDay()->diffInDays($today, false);
                $hotel->contract_ends_on = $hotel->contract_ends_on->copy()->addDays($offset);
            }

            $hotel->contract_starts_on = $today;
            $hotel->approved_at = Date::now();
            $hotel->approved_by = $approver->id;
            $hotel->access_state = HotelAccessState::Active;

            AuditLog::record($hotel, 'hotel.approved');
            $hotel->save();

            $manager = $this->requestedManager($hotel);

            if ($manager !== null) {
                $manager->status = AccountStatus::Active;
                AuditLog::record($manager, 'manager.activated_from_hotel_approval', [
                    'hotel_id' => $hotel->id,
                    'hotel_name' => $hotel->name,
                ]);
                $manager->save();

                if ($manager->email !== null) {
                    $hotelId = $hotel->id;
                    $managerId = $manager->id;

                    DB::afterCommit(function () use ($hotelId, $managerId): void {
                        $approvedHotel = Hotel::query()->withoutGlobalScopes()->find($hotelId);
                        $approvedManager = User::query()->find($managerId);

                        if ($approvedHotel === null || $approvedManager === null || $approvedManager->email === null) {
                            return;
                        }

                        Mail::to($approvedManager->email, $approvedManager->name)
                            ->queue(new HotelApprovedMail($approvedHotel, $approvedManager));
                    });
                }
            }

            return $hotel;
        });
    }

    /**
     * Reject a pending hotel: it is archived with the reason. There is no
     * rejected state; the audit action tells a rejection apart later
     * (spec 0002, AC-14).
     */
    public function reject(Hotel $hotel, string $reason): Hotel
    {
        $this->expect($hotel, HotelAccessState::Pending, 'reject');

        return $this->change($hotel, 'hotel.rejected', function (Hotel $hotel) use ($reason): void {
            $hotel->archived_at = Date::now();
            $hotel->archive_reason = $reason;
            $hotel->access_state = HotelAccessState::Archived;
        }, ['reason' => $reason]);
    }

    /**
     * Archive: access stops, every row stays (DATA-10; spec 0002, invariant 2).
     */
    public function archive(Hotel $hotel, ?string $reason = null): Hotel
    {
        if ($hotel->access_state === HotelAccessState::Archived) {
            throw HotelStateConflict::for($hotel, 'archive', 'not archived');
        }

        return $this->change($hotel, 'hotel.archived', function (Hotel $hotel) use ($reason): void {
            $hotel->archived_at = Date::now();
            $hotel->archive_reason = $reason;
            $hotel->access_state = HotelAccessState::Archived;
        }, $reason === null ? [] : ['reason' => $reason]);
    }

    /**
     * Move the contract end. A hotel whose contract had ended comes back on
     * its own, because the status is derived (spec 0002, State transitions).
     */
    public function extendContract(Hotel $hotel, CarbonInterface $endsOn): Hotel
    {
        return $this->change($hotel, 'hotel.contract_extended', function (Hotel $hotel) use ($endsOn): void {
            $hotel->contract_ends_on = $endsOn->copy()->startOfDay();
        });
    }

    /**
     * Freeze the clock (spec 0002, AC-5).
     */
    public function pause(Hotel $hotel): Hotel
    {
        $this->expect($hotel, HotelAccessState::Active, 'pause');

        return $this->change($hotel, 'hotel.paused', function (Hotel $hotel): void {
            $hotel->paused_at = Date::now();
            $hotel->access_state = HotelAccessState::Paused;
        });
    }

    /**
     * Give back exactly the days that were paused: whole calendar days
     * between paused_at and now, both through startOfDay(), so days remaining
     * is what it was before the pause (spec 0002, AC-5, invariant 8).
     */
    public function resume(Hotel $hotel): Hotel
    {
        $this->expect($hotel, HotelAccessState::Paused, 'resume');

        return $this->change($hotel, 'hotel.resumed', function (Hotel $hotel): void {
            $pausedAt = $hotel->paused_at ?? Date::now();
            $pausedDays = (int) $pausedAt->copy()->startOfDay()->diffInDays(Date::now()->startOfDay(), false);

            if ($hotel->contract_ends_on !== null && $pausedDays > 0) {
                $hotel->contract_ends_on = $hotel->contract_ends_on->copy()->addDays($pausedDays);
            }

            $hotel->paused_at = null;
            $hotel->access_state = HotelAccessState::Active;
        });
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Apply one change and its audit row in one transaction.
     *
     * The audit row is written while the attributes are still dirty, so the
     * before and after values come from Eloquent, never rebuilt by hand
     * (spec 0002, Value sourcing).
     *
     * @param  callable(Hotel): void  $mutate
     * @param  array<string, mixed>  $extra
     */
    private function change(Hotel $hotel, string $action, callable $mutate, array $extra = []): Hotel
    {
        return DB::transaction(function () use ($hotel, $action, $mutate, $extra): Hotel {
            $mutate($hotel);
            AuditLog::record($hotel, $action, $extra);
            $hotel->save();

            return $hotel;
        });
    }

    private function expect(Hotel $hotel, HotelAccessState $state, string $action): void
    {
        if ($hotel->access_state !== $state) {
            throw HotelStateConflict::for($hotel, $action, $state->value);
        }
    }

    /**
     * A slug derived from the name, made unique by a counter (spec 0002,
     * Value sourcing). Checked without the tenant scope: a slug clash with
     * another hotel is a clash whoever is signed in.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'hotel';
        $slug = $base;
        $counter = 2;

        while (Hotel::withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    /**
     * The manager account that came with a public hotel request, if one exists.
     */
    private function requestedManager(Hotel $hotel): ?User
    {
        return User::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', AccountStatus::Inactive->value)
            ->whereNull('created_by')
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->where('name', Role::Manager->value))
            ->orderBy('id')
            ->first();
    }

    private function defaultPlanId(): int
    {
        $standard = SubscriptionPlan::query()->active()->where('slug', 'standard')->first();

        return (int) ($standard ?? SubscriptionPlan::query()->active()->orderBy('employee_limit')->orderBy('id')->firstOrFail())->id;
    }
}

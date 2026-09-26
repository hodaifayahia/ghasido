<?php

namespace App\Services\Hotels;

use App\Enums\CapacityState;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\SeatQuota;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Seat quotas per department (SUB-01, SUB-02, SUB-03; spec 0002, AC-6, AC-7).
 *
 * Two rules are easy to get backwards and are settled here:
 *
 * 1. Lowering a quota below current usage is ALLOWED. It touches no account
 *    and only refuses the next one (SUB-03; spec 0002, invariant 7).
 * 2. The refusal happens at account creation time, on the server, through
 *    assertSeatAvailable(). The dialog never blocks a lowering.
 */
class SeatQuotaService
{
    /**
     * Save the full set of quotas for one hotel and write ONE audit row in the
     * batch shape, listing only the departments whose allowed seats actually
     * changed (spec 0002, AC-16, Value sourcing).
     *
     * A department with no row and 0 requested seats gets no row: a hotel's
     * departments are its seat_quotas rows (AC-8). An existing row set to 0
     * is kept at 0, because nothing is deleted.
     *
     * @param  list<array{department_id: int, allowed_seats: int}>  $quotas
     * @return array<int, array{from: int, to: int}> the changes, keyed by department id
     */
    public function sync(Hotel $hotel, array $quotas): array
    {
        return DB::transaction(function () use ($hotel, $quotas): array {
            /** @var array<int, SeatQuota> $existing */
            $existing = $hotel->seatQuotas()->get()->keyBy('department_id')->all();
            $changes = [];

            foreach ($quotas as $quota) {
                $departmentId = $quota['department_id'];
                $allowed = max(0, $quota['allowed_seats']);
                $row = $existing[$departmentId] ?? null;

                if ($row === null) {
                    if ($allowed === 0) {
                        continue;
                    }

                    $hotel->seatQuotas()->create([
                        'department_id' => $departmentId,
                        'allowed_seats' => $allowed,
                    ]);
                    $changes[$departmentId] = ['from' => 0, 'to' => $allowed];

                    continue;
                }

                if ($row->allowed_seats === $allowed) {
                    continue;
                }

                $changes[$departmentId] = ['from' => $row->allowed_seats, 'to' => $allowed];
                $row->allowed_seats = $allowed;
                $row->save();
            }

            if ($changes !== []) {
                AuditLog::recordQuotaChange($hotel, 'hotel.seats_updated', $changes);
            }

            return $changes;
        });
    }

    /**
     * Refuse the next account in a department that is at or over its quota
     * (SUB-02, SUB-03; spec 0002, AC-7).
     *
     * This is what gives the quota teeth. The Employees screen calls it before
     * creating an account; nothing else in this spec does, so a passing test
     * here is not end to end proof (spec 0002, Consequences).
     *
     * @throws ValidationException naming the department
     */
    public function assertSeatAvailable(Hotel $hotel, Department $department): void
    {
        $plan = $hotel->subscriptionPlan()->first();
        $usedAtHotel = $hotel->usedSeats();

        if ($plan === null || $usedAtHotel >= $plan->employee_limit) {
            throw ValidationException::withMessages([
                'department_id' => __('This hotel has reached its :plan employee limit (:used/:allowed). Contact the administrator to change its subscription.', [
                    'plan' => $plan->name ?? __('subscription'),
                    'used' => $usedAtHotel,
                    'allowed' => $plan->employee_limit ?? 0,
                ]),
            ]);
        }

        $quota = $hotel->seatQuotas()
            ->where('department_id', $department->id)
            ->first();

        $allowed = $quota->allowed_seats ?? 0;
        $used = $quota?->usedSeats() ?? 0;

        if ($quota !== null && CapacityState::compare($used, $allowed) === CapacityState::Available) {
            return;
        }

        throw ValidationException::withMessages([
            'department_id' => __(
                'Seat limit reached for :department at :hotel (:used/:allowed).',
                [
                    'department' => $department->name,
                    'hotel' => $hotel->name,
                    'used' => $used,
                    'allowed' => $allowed,
                ],
            ),
        ]);
    }
}

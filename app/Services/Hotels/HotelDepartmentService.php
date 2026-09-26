<?php

namespace App\Services\Hotels;

use App\Enums\AccountStatus;
use App\Enums\DepartmentStatus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\SeatQuota;
use App\Models\User;
use App\Services\Departments\DepartmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Which departments a hotel runs (SUB-01, ORG-02, ORG-03). A hotel "has" a
 * department when it holds a seat_quotas row for it; this adds and removes
 * that row directly, where Manage seats only edits the numbers.
 *
 * Removing never touches an employee (DATA-10): it is refused while the
 * department still has active employees at the hotel, and inactive ones keep
 * their department on their row.
 */
class HotelDepartmentService
{
    public function __construct(private readonly DepartmentService $departments) {}

    /**
     * Link an existing department (shared, or the hotel's own), or create a
     * new department owned by the hotel and link it. A department already
     * linked just gets the new seat number.
     */
    public function add(Hotel $hotel, ?Department $department, ?string $newName, int $seats): Department
    {
        return DB::transaction(function () use ($hotel, $department, $newName, $seats): Department {
            if ($department === null) {
                $department = $this->departments->create([
                    'name' => trim((string) $newName),
                    'slug' => '',
                    'focus' => null,
                    'status' => DepartmentStatus::Active,
                    'hotel_id' => $hotel->id,
                ]);
            }

            $quota = SeatQuota::query()
                ->where('hotel_id', $hotel->id)
                ->where('department_id', $department->id)
                ->first() ?? new SeatQuota(['hotel_id' => $hotel->id, 'department_id' => $department->id]);

            $from = $quota->exists ? $quota->allowed_seats : 0;
            $quota->allowed_seats = $seats;
            $quota->save();

            AuditLog::recordQuotaChange($hotel, 'hotel.department_added', [
                $department->id => ['from' => $from, 'to' => $seats],
            ]);

            return $department;
        });
    }

    /**
     * Take a department off the hotel: its seat quota row goes, the
     * department itself and every employee row stay.
     */
    public function remove(Hotel $hotel, Department $department): void
    {
        $active = User::query()
            ->where('hotel_id', $hotel->id)
            ->where('department_id', $department->id)
            ->where('status', AccountStatus::Active->value)
            ->count();

        if ($active > 0) {
            throw ValidationException::withMessages([
                'department' => trans_choice(
                    ':department still has :count active employee at :hotel. Move or deactivate them first.|:department still has :count active employees at :hotel. Move or deactivate them first.',
                    $active,
                    ['department' => $department->name, 'hotel' => $hotel->name],
                ),
            ]);
        }

        DB::transaction(function () use ($hotel, $department): void {
            $quota = SeatQuota::query()
                ->where('hotel_id', $hotel->id)
                ->where('department_id', $department->id)
                ->first();

            if ($quota === null) {
                return;
            }

            $from = $quota->allowed_seats;
            $quota->delete();

            AuditLog::recordQuotaChange($hotel, 'hotel.department_removed', [
                $department->id => ['from' => $from, 'to' => 0],
            ]);
        });
    }
}

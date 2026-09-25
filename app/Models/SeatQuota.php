<?php

namespace App\Models;

use App\Concerns\ScopedToHotel;
use App\Enums\AccountStatus;
use App\Enums\CapacityState;
use App\Enums\Role;
use Database\Factories\SeatQuotaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * How many employee seats a hotel bought in one department (SUB-01).
 *
 * @property int $id
 * @property int $hotel_id
 * @property int $department_id
 * @property int $allowed_seats
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $used_seats_count
 */
#[Fillable(['hotel_id', 'department_id', 'allowed_seats'])]
class SeatQuota extends Model
{
    /** @use HasFactory<SeatQuotaFactory> */
    use HasFactory, ScopedToHotel;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_seats' => 'integer',
        ];
    }

    /** @return BelongsTo<Hotel, $this> */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Active employee accounts in this hotel and department (spec 0002, AC-6).
     *
     * Counted live, never stored. Prefers an eager loaded count so a quota
     * list is one query rather than one per department.
     */
    public function usedSeats(): int
    {
        if ($this->used_seats_count !== null) {
            return $this->used_seats_count;
        }

        return User::query()
            ->where('hotel_id', $this->hotel_id)
            ->where('department_id', $this->department_id)
            ->where('status', AccountStatus::Active->value)
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', Role::Employee->value))
            ->count();
    }

    public function capacityState(): CapacityState
    {
        return CapacityState::compare($this->usedSeats(), $this->allowed_seats);
    }

    /**
     * Is this department at or past its limit?
     *
     * Drives the seat limit alert sentence in the hotel sidebar.
     */
    public function isAtOrOverLimit(): bool
    {
        return $this->capacityState() !== CapacityState::Available;
    }
}

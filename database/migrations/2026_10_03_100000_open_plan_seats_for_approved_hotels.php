<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hotels approved before approval opened the plan's seats (client report
 * 2026-10-02: "as a manager I found no way to add an employee"): an active
 * hotel with no department seats gets every active shared department, each
 * allowed up to its plan's employee limit, as HotelService::approve now
 * does. A hotel that already has seats is left as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        $departments = DB::table('departments')
            ->whereNull('hotel_id')
            ->where('is_active', true)
            ->pluck('id');

        if ($departments->isEmpty()) {
            return;
        }

        $hotels = DB::table('hotels')
            ->join('subscription_plans', 'subscription_plans.id', '=', 'hotels.subscription_plan_id')
            ->where('hotels.access_state', 'active')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('seat_quotas')->whereColumn('seat_quotas.hotel_id', 'hotels.id'))
            ->where('subscription_plans.employee_limit', '>', 0)
            ->get(['hotels.id', 'subscription_plans.employee_limit']);

        $now = now();

        foreach ($hotels as $hotel) {
            DB::table('seat_quotas')->insert($departments->map(fn ($id): array => [
                'hotel_id' => $hotel->id,
                'department_id' => $id,
                'allowed_seats' => $hotel->employee_limit,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }
    }

    public function down(): void
    {
        // The seats stay: removing them would block the managers again.
    }
};

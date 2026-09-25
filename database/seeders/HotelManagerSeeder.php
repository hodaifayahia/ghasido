<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A usable local manager account for the seeded hotel portfolio (ROLE-01,
 * ROLE-02, SUB-02, REM-07; spec 0003 Part D).
 *
 * This is intentionally called only from DatabaseSeeder's local/testing
 * portfolio. It gives the employee-management lane a real account to test
 * without placing a shared password on a production database.
 */
class HotelManagerSeeder extends Seeder
{
    public function run(): void
    {
        $hotel = Hotel::withoutGlobalScopes()
            ->where('slug', 'la-gazelle-dor')
            ->first();

        if ($hotel === null) {
            return;
        }

        $manager = User::firstOrNew(['email' => 'manager@guesvia.test']);

        if (! $manager->exists) {
            $manager->password = 'password';
        }

        $manager->fill([
            'name' => 'Hotel Manager',
            'username' => 'manager',
            'hotel_id' => $hotel->id,
            'department_id' => null,
            'status' => AccountStatus::Active,
        ]);
        $manager->save();
        $manager->setRole(Role::Manager);
    }
}

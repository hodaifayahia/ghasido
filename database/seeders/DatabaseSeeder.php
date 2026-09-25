<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
        ]);

        // The starter's convenience account. `composer setup` now seeds as
        // well as migrates, so this is confined to a developer machine: a
        // known email on a known password has no business on a real database.
        $isDevelopmentMachine = app()->environment('local', 'testing');

        if ($isDevelopmentMachine && ! User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        // The harness Super Admin the visual-verification lanes log in as
        // (spec 0003 Part I, G.7). Same confinement, same reason.
        if ($isDevelopmentMachine && ! User::where('email', 'harness@guesvia.test')->exists()) {
            User::factory()->create([
                'name' => 'Harness Owner',
                'email' => 'harness@guesvia.test',
                'username' => 'harness',
                'password' => 'password',
            ])->setRole(Role::SuperAdmin);
        }

        // A realistic portfolio to build against, then the learning content
        // and progress on top of it. Kept to a developer machine: real hotels
        // and 176 employee accounts have no business on a client database
        // (spec 0002, AC-19; spec 0003 Part G).
        if ($isDevelopmentMachine) {
            $this->call([
                HotelPortfolioSeeder::class,
                HotelManagerSeeder::class,
                LearningContentSeeder::class,
            ]);
        }

        // WithoutModelEvents mutes the package's own cache invalidation for
        // every row seeded through this class, including through the nested
        // call() above, so the cache is cleared here by hand (spec 0001,
        // invariant 7). Without this, the first request after seeding can be
        // refused on a stale cache.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

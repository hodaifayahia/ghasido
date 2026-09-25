<?php

namespace Tests\Feature;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Seeding the way a real database is seeded (spec 0001, AC-3, AC-10).
 *
 * `composer setup` now runs `db:seed --force`, so DatabaseSeeder is the path
 * production, staging and CI take. It differs from calling a seeder directly:
 * WithoutModelEvents mutes the package's own cache invalidation for every row
 * written through it, which is invariant 7 and is easy to regress.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_through_the_database_seeder_builds_the_whole_matrix(): void
    {
        // Start from nothing, so this does not lean on the roles TestCase
        // already seeded.
        Role::query()->delete();
        Permission::query()->delete();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, Role::count());
        $this->assertSame(count(PermissionEnum::cases()), Permission::count());

        // The failure this guards: with a stale permission cache the roles
        // are created but hold no permissions, or the seeder throws
        // PermissionDoesNotExist part way through.
        $this->assertCount(
            count(PermissionEnum::cases()),
            Role::findByName(RoleEnum::SuperAdmin->value)->permissions,
        );
        $this->assertCount(
            count(RoleEnum::Manager->permissionNames()),
            Role::findByName(RoleEnum::Manager->value)->permissions,
        );

        $manager = User::where('email', 'manager@guesvia.test')->first();

        $this->assertNotNull($manager);
        $this->assertSame('manager', $manager->username);
        $this->assertTrue($manager->hasRole(RoleEnum::Manager->value));
        $this->assertNotNull($manager->hotel_id);
    }

    public function test_a_freshly_seeded_role_answers_permission_checks_straight_away(): void
    {
        Role::query()->delete();
        Permission::query()->delete();

        $this->seed(DatabaseSeeder::class);

        $manager = User::factory()->manager()->create();

        $this->assertTrue($manager->can(PermissionEnum::EmployeesView->value));
        $this->assertTrue($manager->can(PermissionEnum::HotelsView->value));
    }

    public function test_seeding_twice_does_not_duplicate_anything(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, Role::count());
        $this->assertSame(count(PermissionEnum::cases()), Permission::count());
        $this->assertSame(1, User::where('email', 'test@example.com')->count());
    }
}

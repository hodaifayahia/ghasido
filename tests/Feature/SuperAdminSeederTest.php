<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The first platform owner (spec 0001, AC-10).
 */
class SuperAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_owner_when_all_three_values_are_set()
    {
        $this->configureOwner('Houdaifa', 'owner@guesvia.test', 'initial-password');

        $this->seed(SuperAdminSeeder::class);

        $owner = User::where('email', 'owner@guesvia.test')->first();

        $this->assertNotNull($owner);
        $this->assertSame('Houdaifa', $owner->name);
        $this->assertTrue($owner->hasRole(Role::SuperAdmin->value));
        $this->assertTrue(Hash::check('initial-password', $owner->password));
    }

    public function test_it_does_nothing_when_a_value_is_blank()
    {
        // Blank means skip, so `composer setup` succeeds on a fresh clone
        // without quietly creating an account.
        $this->configureOwner('Houdaifa', '', 'initial-password');

        $this->seed(SuperAdminSeeder::class);

        $this->assertSame(0, User::count());
    }

    public function test_it_does_nothing_when_the_values_are_missing_entirely()
    {
        $this->configureOwner(null, null, null);

        $this->seed(SuperAdminSeeder::class);

        $this->assertSame(0, User::count());
    }

    public function test_a_second_run_leaves_one_account_and_keeps_the_new_password()
    {
        $this->configureOwner('Houdaifa', 'owner@guesvia.test', 'initial-password');
        $this->seed(SuperAdminSeeder::class);

        // The owner changes their password, then the seeder runs again on a
        // later deploy. It must not reset what they chose.
        $owner = User::where('email', 'owner@guesvia.test')->firstOrFail();
        $owner->forceFill(['password' => 'chosen-by-the-owner'])->save();

        $this->configureOwner('Houdaifa Renamed', 'owner@guesvia.test', 'initial-password');
        $this->seed(SuperAdminSeeder::class);

        $this->assertSame(1, User::where('email', 'owner@guesvia.test')->count());

        $owner->refresh();
        $this->assertSame('Houdaifa Renamed', $owner->name);
        $this->assertTrue(Hash::check('chosen-by-the-owner', $owner->password));
        $this->assertTrue($owner->hasRole(Role::SuperAdmin->value));
    }

    private function configureOwner(?string $name, ?string $email, ?string $password): void
    {
        config([
            'guesvia.super_admin.name' => $name,
            'guesvia.super_admin.email' => $email,
            'guesvia.super_admin.password' => $password,
        ]);
    }
}

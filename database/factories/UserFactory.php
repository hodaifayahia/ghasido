<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'ai_points_allocated' => 2000,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * The platform owner: passes every authorization check (ROLE-03).
     */
    public function superAdmin(): static
    {
        return $this->withRole(Role::SuperAdmin);
    }

    /**
     * A hotel administrator who can manage the hotel's departments and
     * employee accounts, but not platform-wide settings.
     */
    public function admin(): static
    {
        return $this->withRole(Role::Admin);
    }

    /**
     * A hotel manager.
     */
    public function manager(): static
    {
        return $this->withRole(Role::Manager);
    }

    /**
     * A learner. Holds none of the admin permissions.
     */
    public function employee(): static
    {
        return $this->withRole(Role::Employee);
    }

    /**
     * A login username in the `first.last` style the seeder uses (AUTH-01):
     * lowercase, `[a-z0-9._-]`, unique through a numeric suffix.
     */
    public function withUsername(?string $username = null): static
    {
        return $this->state(fn (array $attributes) => [
            'username' => $username ?? Str::of((string) ($attributes['name'] ?? fake()->name()))
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', '.')
                ->trim('.')
                ->limit(30, '')
                ->append('.'.fake()->unique()->numberBetween(1, 99999))
                ->toString(),
        ]);
    }

    /**
     * Consented to reminder emails, dated (REM-05, PRIV-02).
     */
    public function consented(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_consent_at' => now(),
        ]);
    }

    /**
     * Past the first-login screen: email confirmed, research notice
     * acknowledged (AUTH-04, PRIV-01).
     */
    public function firstLoginDone(): static
    {
        return $this->state(fn (array $attributes) => [
            'first_login_completed_at' => now(),
            'research_notice_acknowledged_at' => now(),
            'last_login_at' => now(),
        ]);
    }

    /**
     * Bound to exactly one hotel and one department (ORG-03).
     */
    public function forHotel(Hotel $hotel, Department $department): static
    {
        return $this->state(fn (array $attributes) => [
            'hotel_id' => $hotel->id,
            'department_id' => $department->id,
        ]);
    }

    /**
     * Assign a role after the user exists.
     *
     * Roles live in a pivot table, not a column, so this runs afterCreating
     * rather than as a state attribute. It goes through User::setRole() so the
     * one role invariant holds here too (spec 0001, invariant 1).
     */
    private function withRole(Role $role): static
    {
        return $this->afterCreating(
            fn (User $user) => $user->setRole($role),
        );
    }
}

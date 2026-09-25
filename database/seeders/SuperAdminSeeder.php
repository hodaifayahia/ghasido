<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The first platform owner (spec 0001, AC-10).
 *
 * Reads config/guesvia.php, which reads the three SUPER_ADMIN_* variables.
 * Any of them blank and the seeder does nothing, so `composer setup` succeeds
 * on a fresh clone without creating an account.
 *
 * Safe to run repeatedly: keyed on email, so a second run leaves one account.
 * The name and the role are refreshed every run; the password is written only
 * when the account is created, so a re-run never resets a password the owner
 * has since changed.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $name = $this->setting('name');
        $email = $this->setting('email');
        $password = $this->setting('password');

        if ($name === null || $email === null || $password === null) {
            // isset(), not ?->: Seeder::$command is an uninitialised property
            // rather than a nullable one, which is how the framework itself
            // guards it (Illuminate\Database\Seeder lines 52, 61, 136).
            // Larastan reads the framework's @var PHPDoc, which claims the
            // property is always set, so it calls the guard redundant. It is
            // not: the property has no value until setCommand() runs, and
            // dropping the guard is a fatal error when a seeder is built
            // outside the console.
            // @phpstan-ignore isset.property
            if (isset($this->command)) {
                $this->command->info(
                    'SuperAdminSeeder: SUPER_ADMIN_NAME, SUPER_ADMIN_EMAIL and '
                    .'SUPER_ADMIN_PASSWORD are not all set, so no account was created.',
                );
            }

            return;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $name;

        if (! $user->exists) {
            // The `password` cast hashes this on write.
            $user->password = $password;
        }

        $user->save();
        $user->setRole(Role::SuperAdmin);
    }

    /**
     * One SUPER_ADMIN_* value, or null when it is missing or blank.
     */
    private function setting(string $key): ?string
    {
        $value = config("guesvia.super_admin.{$key}");

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}

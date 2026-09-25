<?php

namespace App\Console\Commands;

use App\Models\Owner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Create the platform owner's console login, or reset its password when the
 * e-mail already exists (spec 0007, D1). The only way an owner account is
 * made: there is no sign-up page and no e-mail reset.
 */
#[Signature('owner:create {email : The owner\'s e-mail, used to sign in at /owner/login} {--name= : Display name} {--password= : Skip the prompt (e.g. a deploy script); prefer the prompt on a shared shell}')]
#[Description('Create the owner console login, or reset its password')]
class CreateOwner extends Command
{
    public const int MIN_PASSWORD = 12;

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $password = $this->option('password');
        $password = is_string($password) && $password !== '' ? $password : (string) $this->secret('Password (at least '.self::MIN_PASSWORD.' characters)');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'min:'.self::MIN_PASSWORD, 'max:255']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $owner = Owner::query()->firstOrNew(['email' => $email]);
        $created = ! $owner->exists;
        $name = $this->option('name');

        $owner->name = is_string($name) && trim($name) !== '' ? trim($name) : ($owner->exists ? $owner->name : Str::before($email, '@'));
        $owner->password = $password;
        $owner->save();

        $this->info($created
            ? "Owner {$email} created. Sign in at ".route('owner.login')
            : "Password reset for owner {$email}.");

        return self::SUCCESS;
    }
}

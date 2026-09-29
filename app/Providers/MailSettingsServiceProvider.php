<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Services\Mail\MailSettings;
use Illuminate\Mail\MailManager;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Settings → Email (client request 2026-09-29) over config('mail.*').
 *
 * - On a web request, the stored mailbox is applied the first time the mail
 *   manager is resolved, so pages that send nothing never read it.
 * - In a queue worker (long-running), it is read again before every job and
 *   the cached mailers are dropped, so a password saved a minute ago is the
 *   one the next email uses.
 *
 * With nothing saved, .env applies unchanged.
 */
class MailSettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(MailSettings::class);

        $this->app->afterResolving('mail.manager', function (): void {
            $this->app->make(MailSettings::class)->apply();
        });
    }

    public function boot(): void
    {
        // Settings → Email: the Super Admin only (ROLE-01, SEC-01). Written
        // out, like manage-ai-models, so it stands without Gate::before.
        Gate::define('manage-mail-settings', fn (User $user): bool => $user->hasRole(Role::SuperAdmin->value));

        Event::listen(JobProcessing::class, function (): void {
            $settings = $this->app->make(MailSettings::class);
            $settings->refresh();
            $settings->apply();

            if ($this->app->resolved('mail.manager')) {
                /** @var MailManager $manager */
                $manager = $this->app->make('mail.manager');
                $manager->forgetMailers();
            }
        });
    }
}

<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\MailSettingsUpdateRequest;
use App\Mail\TestEmailMail;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Mail\MailDelivery;
use App\Services\Mail\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Settings → Email (client request 2026-09-29): the Super Admin sets the
 * mailbox every email is sent from (contact@ghasido.com on Hostinger),
 * switches between real sending and log only, and sends a test email that
 * reports the mail server's answer in plain words. Guarded by the
 * `manage-mail-settings` gate on the route and again here (ROLE-01).
 * The password never leaves the server (SEC-03).
 */
final class MailSettingsController extends Controller
{
    public function edit(Request $request, MailSettings $settings): Response
    {
        Gate::authorize('manage-mail-settings');

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/Email', [
            'settings' => $settings->payload(),
            'defaultTestTo' => (string) ($user->email ?? ''),
        ]);
    }

    public function update(MailSettingsUpdateRequest $request, MailSettings $settings): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $passwordChanged = trim((string) $request->input('password')) !== '';
        $row = $settings->update($request->validated(), $user);

        // Never the password: only whether it changed (SEC-03, SEC-06).
        AuditLog::record($row, 'mail-settings.updated', [
            'values' => MailSettings::auditValues($row),
            'password_changed' => $passwordChanged,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email settings saved. New emails use them at once.')]);

        return back();
    }

    public function test(Request $request, MailSettings $settings): RedirectResponse
    {
        Gate::authorize('manage-mail-settings');

        $data = $request->validate(['to' => ['required', 'string', 'email', 'max:255']]);
        $to = (string) $data['to'];

        $settings->refresh();
        $settings->apply();
        app('mail.manager')->forgetMailers();

        $mailer = (string) config('mail.default');
        $server = $mailer === 'smtp'
            ? config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port')
            : __('Log only (nothing is sent)');
        $from = trim(config('mail.from.name').' <'.config('mail.from.address').'>');

        try {
            // send(), not queue(): the answer is the mail server's, now.
            Mail::to($to)
                ->locale(app()->getLocale())
                ->send(new TestEmailMail($server, $from, Date::now()->format('Y-m-d H:i')));

            $result = [
                'status' => 'ok',
                'to' => $to,
                'mailer' => $mailer,
                'message' => $mailer === 'smtp'
                    ? __('The mail server accepted the test email for :to. Check the inbox (and the spam folder).', ['to' => $to])
                    : __('Log only is on, so the test email was written to the server log and not sent.'),
                'detail' => null,
                'at' => Date::now()->toIso8601String(),
            ];
        } catch (Throwable $exception) {
            $result = [
                'status' => 'failed',
                'to' => $to,
                'mailer' => $mailer,
                'message' => MailDelivery::explain($exception),
                'detail' => MailDelivery::redact($exception->getMessage()),
                'at' => Date::now()->toIso8601String(),
            ];
        }

        $settings->recordTest($result);

        $row = $settings->row();

        if ($row !== null) {
            AuditLog::record($row, 'mail-settings.test_sent', [
                'to' => $to,
                'status' => $result['status'],
                'error' => $result['detail'],
            ]);
        }

        Inertia::flash('toast', [
            'type' => $result['status'] === 'ok' ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return back();
    }
}

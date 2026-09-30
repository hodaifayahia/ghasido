<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\MailSettingsTestRequest;
use App\Http\Requests\Settings\MailSettingsUpdateRequest;
use App\Mail\TestEmailMail;
use App\Models\AuditLog;
use App\Models\MailLog;
use App\Models\User;
use App\Services\Mail\MailConnectionCheck;
use App\Services\Mail\MailDelivery;
use App\Services\Mail\MailLogger;
use App\Services\Mail\MailQueue;
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
    public function edit(Request $request, MailSettings $settings, MailQueue $queue): Response
    {
        Gate::authorize('manage-mail-settings');

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/Email', [
            'settings' => $settings->payload(),
            'defaultTestTo' => (string) ($user->email ?? ''),
            'queue' => $queue->counts(),
            'recent' => MailLog::query()->latest('id')->limit(15)->get()
                ->map(fn (MailLog $log): array => [
                    'id' => $log->id,
                    'to' => $log->to,
                    'subject' => $log->subject,
                    'status' => $log->status,
                    'error' => $log->error,
                    'at' => $log->created_at?->toIso8601String(),
                ])->all(),
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

    /**
     * Check the mail server step by step, then send one test email, with
     * the form as it is on screen (client report 2026-09-30). Nothing is
     * saved except the result.
     */
    public function test(MailSettingsTestRequest $request, MailSettings $settings, MailConnectionCheck $check): RedirectResponse
    {
        // Up to five connection attempts of ten seconds each.
        @set_time_limit(120);

        $data = $request->validated();
        $to = (string) $data['to'];
        $saved = $settings->payload()['values'];
        $row = $settings->row();

        $pick = fn (string $key): mixed => array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '' ? $data[$key] : $saved[$key];

        $mailer = (string) $pick('mailer');
        $host = (string) $pick('host');
        $port = (int) $pick('port');
        $encryption = (string) $pick('encryption');
        $username = trim((string) $pick('username'));
        $typed = (string) ($data['password'] ?? '');
        $password = $typed !== '' ? $typed : $row?->secret();
        $fromAddress = (string) $pick('fromAddress');
        $fromName = (string) $pick('fromName');
        $replyTo = (string) ($data['replyTo'] ?? $saved['replyTo']);

        $unsaved = $row === null || $typed !== ''
            || $mailer !== $saved['mailer'] || $host !== $saved['host'] || $port !== (int) $saved['port']
            || $encryption !== $saved['encryption'] || $username !== $saved['username'] || $fromAddress !== $saved['fromAddress'];

        $steps = [];
        $suggestion = null;

        if ($mailer === 'smtp') {
            if ($password === null || $password === '') {
                $steps[] = ['key' => 'password', 'status' => 'failed', 'message' => __('No password is saved or typed. Type the mailbox password.'), 'detail' => null];
            } else {
                $result = $check->run($host, $port, $encryption, $username !== '' ? $username : null, $password);
                $steps = $result['steps'];
                $suggestion = $result['suggestion'];
            }
        }

        $failedStep = collect($steps)->firstWhere('status', 'failed');

        if ($failedStep !== null) {
            $result = [
                'status' => 'failed',
                'to' => $to,
                'mailer' => $mailer,
                'message' => $failedStep['message'],
                'detail' => $failedStep['detail'],
                'steps' => $steps,
                'suggestion' => $suggestion,
                'unsaved' => $unsaved,
                'at' => Date::now()->toIso8601String(),
            ];
        } else {
            $result = $this->sendTest($to, $mailer, $settings, $host, $port, $encryption, $username, $password, $fromAddress, $fromName, $replyTo, $steps, $unsaved);
        }

        $settings->recordTest($result);

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

    /**
     * Send the emails that waited in the queue (no worker, or a wrong
     * password before), now, with the saved settings.
     */
    public function flush(MailQueue $queue): RedirectResponse
    {
        Gate::authorize('manage-mail-settings');
        @set_time_limit(120);

        $result = $queue->flush();

        Inertia::flash('toast', [
            'type' => $result['failed'] === 0 ? 'success' : 'error',
            'message' => $result['failed'] === 0
                ? trans_choice('{0} No email was waiting.|{1} 1 waiting email was sent.|[2,*] :count waiting emails were sent.', $result['sent'], ['count' => $result['sent']])
                : __(':sent sent, :failed could not be sent. Check Recent emails below for the reason.', ['sent' => $result['sent'], 'failed' => $result['failed']]),
        ]);

        return back();
    }

    /**
     * @param  list<array{key: string, status: string, message: string, detail: string|null}>  $steps
     * @return array<string, mixed>
     */
    private function sendTest(string $to, string $mailer, MailSettings $settings, string $host, int $port, string $encryption, string $username, ?string $password, string $fromAddress, string $fromName, string $replyTo, array $steps, bool $unsaved): array
    {
        app('mail.manager');

        if ($mailer === 'smtp') {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp' => $settings->smtpConfig($host, $port, $encryption, $username !== '' ? $username : null, $password),
            ]);
        } else {
            config(['mail.default' => 'log']);
        }

        config(['mail.from' => ['address' => $fromAddress, 'name' => $fromName !== '' ? $fromName : MailSettings::DEFAULT_NAME]]);

        if ($replyTo !== '') {
            config(['mail.reply_to' => ['address' => $replyTo, 'name' => $fromName]]);
        }

        app('mail.manager')->forgetMailers();

        $server = $mailer === 'smtp' ? $host.':'.$port : __('Log only (nothing is sent)');
        $from = trim($fromName.' <'.$fromAddress.'>');

        try {
            // send(), not queue(): the answer is the mail server's, now.
            Mail::to($to)
                ->locale(app()->getLocale())
                ->send(new TestEmailMail($server, $from, Date::now()->format('Y-m-d H:i')));
        } catch (Throwable $exception) {
            app(MailLogger::class)->failed($to, __('GHASIDO test email'), TestEmailMail::class, $exception);

            return [
                'status' => 'failed',
                'to' => $to,
                'mailer' => $mailer,
                'message' => MailDelivery::explain($exception),
                'detail' => MailDelivery::redact($exception->getMessage()),
                'steps' => [...$steps, ['key' => 'send', 'status' => 'failed', 'message' => MailDelivery::explain($exception), 'detail' => MailDelivery::redact($exception->getMessage())]],
                'suggestion' => null,
                'unsaved' => $unsaved,
                'at' => Date::now()->toIso8601String(),
            ];
        }

        $message = $mailer === 'smtp'
            ? __('The mail server accepted the test email for :to. Check the inbox (and the spam folder).', ['to' => $to])
            : __('Log only is on, so the test email was written to the server log and not sent.');

        if ($unsaved) {
            $message .= ' '.__('These settings are not saved yet: press Save so every email uses them.');
        }

        return [
            'status' => 'ok',
            'to' => $to,
            'mailer' => $mailer,
            'message' => $message,
            'detail' => null,
            'steps' => $mailer === 'smtp' ? [...$steps, ['key' => 'send', 'status' => 'ok', 'message' => __('The test email was accepted.'), 'detail' => null]] : $steps,
            'suggestion' => null,
            'unsaved' => $unsaved,
            'at' => Date::now()->toIso8601String(),
        ];
    }
}

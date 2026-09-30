<?php

namespace App\Http\Controllers\Learn;

use App\Enums\EnglishLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learn\FirstLoginRequest;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The first-login screen (AUTH-04, AUTH-05, AUTH-06, PRIV-01, PRIV-02;
 * spec 0003 Part E).
 *
 * Collects an email (mandatory when the hotel says so), the reminder
 * consent, and the research notice acknowledgement. Consent is a dated
 * timestamp, so granting and revoking are both recorded (REM-05); the same
 * two fields stay editable from the profile afterwards (AUTH-06).
 */
class FirstLoginController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $hotel = $user->hotel()->first();

        return Inertia::render('employee/FirstLogin', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'reminderConsent' => $user->email_consent_at !== null,
                'completed' => $user->hasCompletedFirstLogin(),
            ],
            'requireEmail' => (bool) (($hotel->settings ?? [])['require_email'] ?? false),
            'hotelName' => $hotel?->name,
            'departmentName' => $user->department?->name,
            // Department first, then level (client decision 2026-09-30). The
            // department is chosen here only when the account has none.
            'departments' => $user->department_id === null
                ? FirstLoginRequest::departmentChoices($user)
                    ->map(fn (Department $department): array => ['value' => (string) $department->id, 'label' => $department->name])
                    ->values()
                    ->all()
                : [],
            'level' => $user->english_level?->value,
            'levels' => EnglishLevel::options(),
        ]);
    }

    public function update(FirstLoginRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $level = EnglishLevel::from((string) $request->validated('level'));

        if ($user->department_id === null) {
            $user->department_id = (int) $request->validated('department_id');
        }

        if ($user->english_level !== $level) {
            $user->forceFill([
                'english_level' => $level,
                'english_level_assessed_at' => now(),
                'level_suggestion' => null,
            ]);
        }

        // A returning employee only chose their level: the email, consent and
        // notice they already gave stay as they are.
        if (! $user->hasCompletedFirstLogin()) {
            $email = $request->validated('email');
            $consent = (bool) $request->validated('reminder_consent');

            $user->forceFill([
                'email' => is_string($email) ? $email : null,
                'email_consent_at' => $consent ? ($user->email_consent_at ?? now()) : null,
                'research_notice_acknowledged_at' => $user->research_notice_acknowledged_at ?? now(),
                'first_login_completed_at' => now(),
            ]);
        }

        AuditLog::record($user, 'first_login.completed');

        $user->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Welcome, :name! Your training is ready.', ['name' => $user->name]),
        ]);

        return to_route('learn.home');
    }
}

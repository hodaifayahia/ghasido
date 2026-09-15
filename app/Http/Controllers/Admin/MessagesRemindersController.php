<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Super Admin messages and reminders screen (REM-01, REM-02, REM-03,
 * REM-04, REM-05, REM-06, REM-07, REP-01, ADM-02).
 *
 * Reminder templates, employee consent data and reminder logs are not modelled
 * yet, so this screen returns sample data for a UI-first build.
 */
class MessagesRemindersController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/MessagesReminders', [
            'stats' => [
                [
                    'key' => 'consentedEmployees',
                    'value' => 51,
                    'label' => __('Consent Granted'),
                    'detail' => __('of 72 employees'),
                ],
                [
                    'key' => 'scheduledReminders',
                    'value' => 9,
                    'label' => __('Scheduled Reminders'),
                    'detail' => __('next 7 days'),
                ],
                [
                    'key' => 'followUpNeeded',
                    'value' => 14,
                    'label' => __('Need Follow-up'),
                    'detail' => __('inactive 5+ days'),
                ],
                [
                    'key' => 'sentToday',
                    'value' => 23,
                    'label' => __('Sent Today'),
                    'detail' => __('manual + automatic'),
                ],
                [
                    'key' => 'templates',
                    'value' => 6,
                    'label' => __('Templates Ready'),
                    'detail' => __('email reminder flows'),
                ],
            ],
            'filters' => [
                'hotel' => 'all-hotels',
                'department' => 'all-departments',
                'consent' => 'consent-granted',
                'activity' => 'inactive-5-days',
                'hotels' => [
                    ['value' => 'all-hotels', 'label' => __('All Hotels')],
                    ['value' => 'gazelle', 'label' => 'La Gazelle d\'Or'],
                    ['value' => 'aurassi', 'label' => __('Hotel El Aurassi')],
                ],
                'departments' => [
                    [
                        'value' => 'all-departments',
                        'label' => __('All Departments'),
                    ],
                    ['value' => 'reception', 'label' => __('Reception')],
                    ['value' => 'spa', 'label' => __('Spa')],
                    [
                        'value' => 'housekeeping',
                        'label' => __('Housekeeping'),
                    ],
                ],
                'consents' => [
                    [
                        'value' => 'consent-granted',
                        'label' => __('Consent Granted'),
                    ],
                    [
                        'value' => 'consent-missing',
                        'label' => __('Consent Missing'),
                    ],
                    ['value' => 'all-consent', 'label' => __('All Consent')],
                ],
                'activities' => [
                    ['value' => 'inactive-5-days', 'label' => __('Inactive 5+ days')],
                    ['value' => 'not-started', 'label' => __('Not Started')],
                    ['value' => 'pretest-only', 'label' => __('Pre-test Only')],
                    ['value' => 'all-activity', 'label' => __('All Activity')],
                ],
            ],
            'recipients' => [
                [
                    'id' => 1,
                    'name' => 'Amina Saadi',
                    'hotel' => 'La Gazelle d\'Or',
                    'department' => __('Reception'),
                    'lastActivity' => '28 Aug 2026',
                    'inactivityLabel' => __('2 days ago'),
                    'consentStatus' => 'granted',
                    'status' => 'active',
                ],
                [
                    'id' => 2,
                    'name' => 'Karim Ben Ali',
                    'hotel' => 'La Gazelle d\'Or',
                    'department' => __('Reception'),
                    'lastActivity' => '23 Aug 2026',
                    'inactivityLabel' => __('7 days ago'),
                    'consentStatus' => 'granted',
                    'status' => 'in_progress',
                ],
                [
                    'id' => 3,
                    'name' => 'Sofia Merad',
                    'hotel' => __('Hotel El Aurassi'),
                    'department' => __('Spa'),
                    'lastActivity' => '18 Aug 2026',
                    'inactivityLabel' => __('12 days ago'),
                    'consentStatus' => 'granted',
                    'status' => 'inactive',
                ],
                [
                    'id' => 4,
                    'name' => 'Hicham Zitoune',
                    'hotel' => __('Sheraton Club des Pins'),
                    'department' => __('Housekeeping'),
                    'lastActivity' => 'Never',
                    'inactivityLabel' => __('Not started'),
                    'consentStatus' => 'not_granted',
                    'status' => 'consent_pending',
                ],
                [
                    'id' => 5,
                    'name' => 'Samira Tabi',
                    'hotel' => __('Azure Resort & Spa'),
                    'department' => __('Spa'),
                    'lastActivity' => '27 Aug 2026',
                    'inactivityLabel' => __('3 days ago'),
                    'consentStatus' => 'granted',
                    'status' => 'active',
                ],
            ],
            'templates' => [
                [
                    'id' => 1,
                    'name' => __('Training comeback reminder'),
                    'audience' => __('Inactive employees'),
                    'trigger' => __('5 days without activity'),
                    'updatedAt' => '29 Aug 2026',
                ],
                [
                    'id' => 2,
                    'name' => __('Pre-test completion nudge'),
                    'audience' => __('New starters'),
                    'trigger' => __('First login incomplete'),
                    'updatedAt' => '28 Aug 2026',
                ],
                [
                    'id' => 3,
                    'name' => __('Post-test unlock reminder'),
                    'audience' => __('Completed lessons'),
                    'trigger' => __('Post-test available'),
                    'updatedAt' => '26 Aug 2026',
                ],
            ],
            'automations' => [
                [
                    'id' => 1,
                    'name' => __('Inactive learner follow-up'),
                    'trigger' => __('No progress for 5 days'),
                    'audience' => __('All hotels'),
                    'active' => true,
                ],
                [
                    'id' => 2,
                    'name' => __('Consent missing reminder'),
                    'trigger' => __('Email consent not completed'),
                    'audience' => __('Reception + Spa'),
                    'active' => false,
                ],
            ],
            'logs' => [
                [
                    'id' => 1,
                    'recipient' => 'Karim Ben Ali',
                    'template' => __('Training comeback reminder'),
                    'sentAt' => '30 Aug 2026 · 09:10',
                    'status' => 'sent',
                ],
                [
                    'id' => 2,
                    'recipient' => 'Hicham Zitoune',
                    'template' => __('Pre-test completion nudge'),
                    'sentAt' => '31 Aug 2026 · 08:00',
                    'status' => 'blocked',
                ],
                [
                    'id' => 3,
                    'recipient' => 'Sofia Merad',
                    'template' => __('Training comeback reminder'),
                    'sentAt' => '01 Sep 2026 · 10:30',
                    'status' => 'scheduled',
                ],
            ],
        ]);
    }
}
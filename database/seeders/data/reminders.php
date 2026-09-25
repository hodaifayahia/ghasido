<?php

/*
 * Reminder templates, automation rules and the reminder log (spec 0003 G.7),
 * taken from the Messages & Reminders sample data the screen was built on.
 *
 * `named_employees` renames one seeded portfolio employee per row so the
 * Messages page shows the people its mockup shows; seat counts are unchanged
 * because existing accounts are renamed, never added.
 */

return [
    'templates' => [
        [
            'name' => 'Training comeback reminder',
            'subject' => 'We miss you at Guesvia, {{name}}!',
            'body' => "Hello {{name}},\n\nIt has been a few days since your last lesson at {{hotel}}. Your English training is waiting for you, and you are already {{progress}}% of the way through.\n\nLog in and continue where you left off: {{login_url}}\n\nSee you soon,\nThe Guesvia team",
            'audience_label' => 'Inactive employees',
            'trigger_label' => '5 days without activity',
        ],
        [
            'name' => 'Pre-test completion nudge',
            'subject' => 'Your first step: the short Pre-test',
            'body' => "Hello {{name}},\n\nWelcome to Guesvia! Before your lessons start, please complete the short Pre-test. It takes about 15 to 20 minutes and it is not a pass or fail test.\n\nStart here: {{login_url}}\n\nGood luck,\nThe Guesvia team",
            'audience_label' => 'New starters',
            'trigger_label' => 'First login incomplete',
        ],
        [
            'name' => 'Post-test unlock reminder',
            'subject' => 'Congratulations {{name}}, your Post-test is ready!',
            'body' => "Hello {{name}},\n\nYou have completed all your lessons in the {{department}} programme. Your Post-test is now unlocked: finish it to receive your certificate.\n\nYou have {{days_remaining}} days left in your training period.\n\nStart the Post-test: {{login_url}}\n\nWell done,\nThe Guesvia team",
            'audience_label' => 'Completed lessons',
            'trigger_label' => 'Post-test available',
        ],
    ],

    'rules' => [
        [
            'name' => 'Inactive learner follow-up',
            'trigger' => 'inactive_days',
            'days' => 5,
            'template' => 'Training comeback reminder',
            'audience' => null,
            'audience_label' => 'All hotels',
            'is_active' => true,
        ],
        [
            'name' => 'Consent missing reminder',
            'trigger' => 'consent_missing',
            'days' => null,
            'template' => 'Pre-test completion nudge',
            'audience_departments' => ['Reception', 'Spa'],
            'audience_label' => 'Reception + Spa',
            'is_active' => false,
        ],
    ],

    // Portfolio employees renamed so the Messages recipients match the sample.
    'named_employees' => [
        ['name' => 'Amina Saadi', 'username' => 'amina.saadi', 'hotel' => "La Gazelle d'Or", 'department' => 'Reception', 'consent' => true, 'last_activity_days_ago' => 2],
        ['name' => 'Karim Ben Ali', 'username' => 'karim.ben.ali', 'hotel' => "La Gazelle d'Or", 'department' => 'Reception', 'consent' => true, 'last_activity_days_ago' => 7],
        ['name' => 'Sofia Merad', 'username' => 'sofia.merad', 'hotel' => 'Hotel El Aurassi', 'department' => 'Spa', 'consent' => true, 'last_activity_days_ago' => 12, 'inactive' => true],
        ['name' => 'Hicham Zitoune', 'username' => 'hicham.zitoune', 'hotel' => 'Sheraton Club des Pins', 'department' => 'Housekeeping', 'consent' => false, 'last_activity_days_ago' => null],
        ['name' => 'Samira Tabi', 'username' => 'samira.tabi', 'hotel' => 'Azure Resort & Spa', 'department' => 'Spa', 'consent' => true, 'last_activity_days_ago' => 3],
    ],

    // ~10 log rows: recipient username, template, channel, status, when.
    'log' => [
        ['username' => 'karim.ben.ali', 'template' => 'Training comeback reminder', 'status' => 'sent', 'days_ago' => 1, 'time' => '09:10', 'rule' => 'Inactive learner follow-up'],
        ['username' => 'hicham.zitoune', 'template' => 'Pre-test completion nudge', 'status' => 'blocked', 'days_ago' => 0, 'time' => '08:00'],
        ['username' => 'sofia.merad', 'template' => 'Training comeback reminder', 'status' => 'scheduled', 'days_ahead' => 1, 'time' => '10:30', 'rule' => 'Inactive learner follow-up'],
        ['username' => 'amina.saadi', 'template' => 'Training comeback reminder', 'status' => 'sent', 'days_ago' => 6, 'time' => '09:10', 'rule' => 'Inactive learner follow-up'],
        ['username' => 'samira.tabi', 'template' => 'Training comeback reminder', 'status' => 'sent', 'days_ago' => 4, 'time' => '09:10', 'rule' => 'Inactive learner follow-up'],
        ['username' => 'samira', 'template' => 'Pre-test completion nudge', 'status' => 'sent', 'days_ago' => 3, 'time' => '08:00'],
        ['username' => 'amine', 'template' => 'Training comeback reminder', 'status' => 'sent', 'days_ago' => 9, 'time' => '09:10', 'rule' => 'Inactive learner follow-up'],
        ['username' => 'amine', 'template' => 'Post-test unlock reminder', 'status' => 'scheduled', 'days_ahead' => 2, 'time' => '08:30'],
        ['username' => 'hicham.zitoune', 'template' => 'Training comeback reminder', 'status' => 'blocked', 'days_ago' => 5, 'time' => '09:10', 'rule' => 'Inactive learner follow-up'],
        ['username' => 'sofia.merad', 'template' => 'Pre-test completion nudge', 'status' => 'sent', 'days_ago' => 13, 'time' => '08:00'],
        ['username' => 'karim.ben.ali', 'template' => 'Pre-test completion nudge', 'status' => 'sent', 'days_ago' => 12, 'time' => '08:00'],
    ],
];

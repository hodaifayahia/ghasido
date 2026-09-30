<?php

return [

    /*
    |--------------------------------------------------------------------------
    | First platform owner
    |--------------------------------------------------------------------------
    |
    | The Super Admin account SuperAdminSeeder creates on a real database
    | (spec 0001, AC-10). All three are blank by default, and blank means the
    | seeder does nothing, so `composer setup` succeeds on a fresh clone
    | without quietly creating an account nobody asked for.
    |
    | env() is read here rather than in the seeder, so a cached config in
    | staging or production does not turn these into null (AGENTS.md §6).
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Hotels
    |--------------------------------------------------------------------------
    |
    | Read in exactly one place each, so the stat card, the row pill and the
    | status filter can never disagree with one another (spec 0002, AC-4).
    |
    */

    'hotels' => [
        // How many days before a contract ends counts as expiring soon.
        'expiring_within_days' => 30,

        // Rows per page in the hotels directory.
        'per_page' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Departments
    |--------------------------------------------------------------------------
    */

    'departments' => [
        // Rows per page in the departments directory (spec 0003 Part D).
        'per_page' => 7,
    ],

    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME'),
        'email' => env('SUPER_ADMIN_EMAIL'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI usage limits
    |--------------------------------------------------------------------------
    |
    | Enforced by App\Services\Ai\UsageMeter::assertWithinLimits() BEFORE a
    | job is dispatched, per employee and per hotel, counted from ai_usages
    | rows written today (AIL-01..AIL-04, API-03; spec 0003 Part C).
    |
    */

    'ai' => [
        'limits' => [
            // Role-play guest turns one employee may spend per calendar day.
            'per_employee_daily_turns' => 60,

            // Role-play guest turns one hotel may spend per calendar day.
            'per_hotel_daily_turns' => 2000,

            // Lessons one admin may ask the AI to write per calendar day
            // ("Generate with AI", spec 0004). Checked before dispatch.
            'per_admin_daily_generated_lessons' => (int) env('AI_DAILY_GENERATED_LESSONS', 40),

            // Images one admin may generate per calendar day (spec 0004).
            'per_admin_daily_images' => (int) env('AI_DAILY_IMAGES', 200),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pronunciation check
    |--------------------------------------------------------------------------
    |
    | Score v1 of spec 0006 §5 (App\Services\Pronunciation\PronunciationScorer
    | holds the same values as its defaults). Every attempt stores `version`
    | with its scores: change a threshold and bump the version, so research
    | data scored under two rules is never mixed silently.
    |
    */

    'pronunciation' => [
        'version' => 'v1',

        // Checks one employee may run per calendar day. A check charges no
        // AI points; this cap bounds the STT and coaching cost (AIL-04).
        'daily_checks_per_employee' => (int) env('PRONUNCIATION_DAILY_CHECKS', 300),

        // The longest recording judged; longer ones are refused before any
        // provider is called.
        'max_recording_seconds' => 30,

        'weights' => ['words' => 0.6, 'clarity' => 0.25, 'flow' => 0.15],
        'credit' => [
            'correct' => 1.0,
            'unclear' => 0.7,
            'almost' => 0.5,
            'mispronounced' => 0.25,
            'different' => 0.0,
            'missed' => 0.0,
        ],
        'close_similarity' => 0.5,
        'unclear_floor' => 0.55,
        'unclear_margin' => 0.15,
        'default_reference_confidence' => 0.95,
        'hinted_min_confidence' => 0.3,
        'pause_ms' => 700,
        'ms_per_word' => 380,
        'slow_ratio' => 1.6,
        'penalties' => [
            'slow_per_ratio' => 40,
            'slow_max' => 40,
            'pause' => 10,
            'pause_max' => 30,
            'filler' => 8,
            'filler_max' => 24,
            'extra' => 5,
            'extra_max' => 20,
        ],
        'levels' => ['excellent' => 90, 'good' => 75, 'fair' => 50],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reminders
    |--------------------------------------------------------------------------
    */

    'reminders' => [
        // Days without activity before the inactivity automation fires.
        'inactive_days' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    'tests' => [
        // Default time limit for a seeded Pre-test (TIME-01; spec 0003 G.4).
        'pre_test_time_limit_seconds' => 1200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Show Meaning translations
    |--------------------------------------------------------------------------
    |
    | Made when content is written, never when a learner taps (user request
    | 2026-09-26). Saving a lesson, block, activity, test or course queues one
    | AI draft per new English text; the admin can rewrite any of them. Off,
    | only the admin's "Generate with AI" button and hand-written text remain.
    |
    */

    'meaning' => [
        'auto_translate' => (bool) env('MEANING_AUTO_TRANSLATE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Learner levels
    |--------------------------------------------------------------------------
    |
    | The learner chooses Beginner, Intermediate or Advanced (App\Enums\
    | EnglishLevel). A Pre-test at or above this percentage suggests the next
    | level; the learner decides to move up or stay (client decision
    | 2026-09-30). The Super Admin changes it in Settings → Learning, which
    | wins over this default (PlatformSettings).
    |
    */

    'levels' => [
        'level_up_from' => (int) env('LEVEL_UP_FROM', 70),
    ],

];

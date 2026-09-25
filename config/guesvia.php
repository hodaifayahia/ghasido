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

];

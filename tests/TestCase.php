<?php

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every test starts with the management and learner roles present and a clean permission
     * cache, so a denial can never pass for want of a seeded row and a stale
     * cache can never leak between tests (spec 0001, AC-9, AC-11).
     */
    protected function setUp(): void
    {
        parent::setUp();

        // A developer's .env may point at real, billed providers (Qwen,
        // Deepgram). Tests start on the no-network fakes; a test of a real
        // provider sets its own config and fakes HTTP (API-04).
        config([
            'services.ai.provider' => 'fake',
            'services.ai.image_provider' => 'fake',
            'services.tts.provider' => 'fake',
            'services.stt.provider' => 'fake',
            // Saving content would queue Show Meaning drafts; the tests of
            // that switch it back on themselves.
            'guesvia.meaning.auto_translate' => false,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}

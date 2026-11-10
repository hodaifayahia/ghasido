<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ai:doctor names the AI jobs that failed and what the provider answered
 * (client report 2026-11-08: 129 failures behind "No problem found").
 */
class AiDoctorTest extends TestCase
{
    use RefreshDatabase;

    public function test_recent_failed_jobs_are_a_problem_grouped_by_job_with_the_provider_answer()
    {
        foreach (range(1, 3) as $index) {
            DB::table('failed_jobs')->insert([
                'uuid' => 'uuid-'.$index,
                'connection' => 'database',
                'queue' => 'media',
                'payload' => json_encode(['displayName' => 'App\\Jobs\\GenerateContentImage']),
                'exception' => "Illuminate\\Http\\Client\\RequestException: HTTP request returned status code 403:\n{\"code\":\"AccessDenied\",\"message\":\"Model access denied.\"}\n#0 /app/vendor/foo.php(1): bar()",
                'failed_at' => now(),
            ]);
        }

        $this->artisan('ai:doctor')
            ->expectsOutputToContain('3 AI job(s) failed in the last 24 hours')
            ->expectsOutputToContain('GenerateContentImage ×3')
            ->expectsOutputToContain('Model access denied.')
            ->assertFailed();
    }
}

<?php

namespace Tests\Feature\Seeders;

use App\Enums\ContentStatus;
use App\Models\Course;
use Database\Seeders\EnglishEssentialsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The starter English Essentials topics (client request 2026-10-18) land
 * as drafts for every department, once.
 */
class EnglishEssentialsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_five_starter_topics_as_drafts_once()
    {
        $this->seed(EnglishEssentialsSeeder::class);
        $this->seed(EnglishEssentialsSeeder::class);

        $topics = Course::query()->essentials()->orderBy('position')->get();

        $this->assertSame(
            ['Letters & Spelling', 'Numbers', 'Time & Appointments', 'Greetings', 'Asking for Clarification'],
            $topics->pluck('title')->all(),
        );

        foreach ($topics as $topic) {
            $this->assertSame(ContentStatus::Draft, $topic->status);
            $this->assertNull($topic->department_id);
            $this->assertSame(0, $topic->audienceDepartments()->count());
            $this->assertSame(1, $topic->lessons()->count());
            $this->assertGreaterThan(0, $topic->lessons()->firstOrFail()->blocks()->count());
        }
    }
}

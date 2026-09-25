<?php

namespace Tests\Feature\Learn\Steps;

use App\Enums\BlockType;
use App\Models\Block;
use App\Models\VoiceRecording;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * The Listen & Repeat step (LESSON-06, RESP-05, TEST-07, DATA-02; spec 0003
 * Part E, photo_4): the sentences ship as playable items, and a repeat
 * recording attaches to the block and is stored, never browser-only.
 */
class ListenRepeatStepTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    public function test_the_step_ships_its_sentences(): void
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);

        $lesson = $this->publishedLesson([BlockType::ListenRepeat, BlockType::Complete]);
        $block = $lesson->visibleBlocks()->firstOrFail();

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $block]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('block.type', 'listen_repeat')
                ->has('block.settings.items', 1)
                ->where('block.settings.items.0.text', 'How can I help you?')
            );
    }

    public function test_a_repeat_recording_is_stored_against_the_block(): void
    {
        Storage::fake('local');

        $learner = $this->learner();
        $this->submitPreTest($learner);

        $lesson = $this->publishedLesson([BlockType::ListenRepeat, BlockType::Complete]);
        /** @var Block $block */
        $block = $lesson->visibleBlocks()->firstOrFail();

        $this->actingAs($learner)
            ->postJson(route('learn.recordings.store'), [
                'audio' => UploadedFile::fake()->create('repeat.webm', 40, 'audio/webm'),
                'recordable_type' => 'block',
                'recordable_id' => $block->id,
                'duration_ms' => 4200,
            ])
            ->assertCreated()
            ->assertJsonStructure(['id', 'url', 'duration_ms']);

        $this->assertDatabaseHas('voice_recordings', [
            'user_id' => $learner->id,
            'recordable_type' => (new Block)->getMorphClass(),
            'recordable_id' => $block->id,
        ]);
        $this->assertSame(1, VoiceRecording::query()->count());
    }
}

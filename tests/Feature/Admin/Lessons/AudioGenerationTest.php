<?php

namespace Tests\Feature\Admin\Lessons;

use App\Enums\BlockType;
use App\Jobs\GenerateAudioClip;
use App\Models\AudioClip;
use App\Models\Block;
use App\Models\Lesson;
use App\Services\Content\BlockShaper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Lesson TTS uses the same playable-text inventory for individual block
 * buttons and the platform-wide generation action (CTRL-05, TTS-01..03).
 */
class AudioGenerationTest extends TestCase
{
    use BuildsContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildContent();
    }

    public function test_block_audio_inventory_includes_lesson_sentence_fields()
    {
        $rows = app(BlockShaper::class)->forLesson($this->lesson);
        $listen = collect($rows)->firstWhere('type', BlockType::ListenRepeat->value);

        $this->assertIsArray($listen);
        $this->assertSame('missing', $listen['audio']['How can I help you?']['normal']['status']);
        $this->assertSame('missing', $listen['audio']['How can I help you?']['slow']['status']);
    }

    public function test_super_admin_can_queue_audio_for_every_existing_lesson()
    {
        Queue::fake();

        $second = Lesson::factory()->create([
            'unit_id' => $this->unit->id,
            'title' => 'Second lesson',
        ]);
        Block::factory()->ofType(BlockType::Audio)->create([
            'lesson_id' => $second->id,
            'settings' => ['audio_text' => 'Second lesson phrase'],
        ]);

        $this->actingAs($this->owner)
            ->post(route('audio.generate-all'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audio_clips', [
            'text_hash' => AudioClip::hashFor('How can I help you?'),
            'speed' => 'normal',
        ]);
        $this->assertDatabaseHas('audio_clips', [
            'text_hash' => AudioClip::hashFor('Second lesson phrase'),
            'speed' => 'slow',
        ]);
        Queue::assertPushed(GenerateAudioClip::class, AudioClip::query()->count());
    }
}

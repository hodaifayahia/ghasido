<?php

namespace Tests\Feature\Security;

use App\Enums\BlockType;
use App\Models\Block;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\VoiceRecording;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * Security audit 2026-11-08 (recording-upload-unbounded-disk): every upload
 * stays on the private disk for good (DATA-10), so the route is throttled
 * and one learner's uploads are capped per day (SEC-04).
 */
class AiSpendRecordingUploadTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected Block $block;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
        Storage::fake(MediaAsset::DISK_LOCAL);
        $this->block = $this->publishedLesson([BlockType::Practice])->visibleBlocks()->firstOrFail();
    }

    public function test_recording_uploads_are_capped_per_learner_per_day()
    {
        config()->set('guesvia.recordings.daily_uploads_per_employee', 2);
        $learner = $this->learner();

        $this->upload($learner)->assertCreated();
        $this->upload($learner)->assertCreated();
        $this->upload($learner)->assertStatus(429)->assertJsonStructure(['message']);

        $this->assertSame(2, VoiceRecording::query()->where('user_id', $learner->id)->count());
    }

    public function test_the_recording_upload_route_is_throttled()
    {
        config()->set('guesvia.recordings.daily_uploads_per_employee', 1000);
        $learner = $this->learner();

        for ($i = 0; $i < 20; $i++) {
            $this->upload($learner)->assertCreated();
        }

        $this->upload($learner)->assertStatus(429);
    }

    private function upload(User $learner): TestResponse
    {
        return $this->actingAs($learner)->postJson(route('learn.recordings.store'), [
            'audio' => UploadedFile::fake()->create('answer.wav', 20, 'audio/wav'),
            'recordable_type' => 'block',
            'recordable_id' => $this->block->id,
            'duration_ms' => 1200,
        ]);
    }
}

<?php

namespace Tests\Feature\Learn;

use App\Enums\ActivityType;
use App\Enums\BlockType;
use App\Enums\MediaKind;
use App\Enums\MediaLibrary;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\Block;
use App\Models\MediaAsset;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Voice recordings (TEST-07, DATA-02, RESP-05, PRIV-04, SEC-04; spec 0003
 * Part E).
 */
class RecordingUploadTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();

        Storage::fake(MediaAsset::DISK_LOCAL);
    }

    public function test_a_recording_is_stored_on_the_private_disk_and_returned_as_json()
    {
        $learner = $this->learner();
        $block = $this->publishedLesson([BlockType::Practice])->visibleBlocks()->firstOrFail();

        $response = $this->actingAs($learner)->postJson(route('learn.recordings.store'), [
            'audio' => UploadedFile::fake()->create('answer.wav', 120, 'audio/wav'),
            'recordable_type' => 'block',
            'recordable_id' => $block->id,
            'duration_ms' => 5200,
        ]);

        $response->assertCreated()->assertJsonStructure(['id', 'recording_id', 'url', 'duration_ms']);

        $asset = MediaAsset::query()->findOrFail($response->json('id'));

        $this->assertSame(MediaAsset::DISK_LOCAL, $asset->disk);
        $this->assertSame(MediaLibrary::Recordings, $asset->library);
        $this->assertSame(MediaKind::Audio, $asset->kind);
        $this->assertSame($learner->id, $asset->uploaded_by);
        $this->assertSame($this->hotel->id, $asset->hotel_id);
        $this->assertSame(5200, $asset->duration_ms);
        $this->assertStringStartsWith('recordings/'.$learner->id.'/', $asset->path);
        $this->assertStringEndsWith('.wav', $asset->path);
        $this->assertSame(route('media.show', $asset), $response->json('url'));

        Storage::disk(MediaAsset::DISK_LOCAL)->assertExists($asset->path);

        $this->assertDatabaseHas('voice_recordings', [
            'user_id' => $learner->id,
            'recordable_type' => (new Block)->getMorphClass(),
            'recordable_id' => $block->id,
            'media_asset_id' => $asset->id,
            'duration_ms' => 5200,
        ]);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $learner->id, 'action' => 'recording.uploaded']);
    }

    public function test_an_unsupported_file_type_and_an_unknown_recordable_are_refused()
    {
        $learner = $this->learner();

        $this->actingAs($learner)->postJson(route('learn.recordings.store'), [
            'audio' => UploadedFile::fake()->create('answer.pdf', 10, 'application/pdf'),
            'recordable_type' => 'block',
            'recordable_id' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['audio']);

        $this->actingAs($learner)->postJson(route('learn.recordings.store'), [
            'audio' => UploadedFile::fake()->create('answer.wav', 10, 'audio/wav'),
            'recordable_type' => 'user',
            'recordable_id' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['recordable_type']);

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_a_test_sitting_takes_a_recording_only_from_its_own_learner_while_open()
    {
        // The test runner records against the open sitting (TEST-07; spec
        // 0005 §1.6): before this, it sent id 0 and every upload failed.
        $learner = $this->learner();
        $sitting = TestAttempt::factory()->create([
            'user_id' => $learner->id,
            'test_id' => $this->publishedPreTest()->id,
            'submitted_at' => null,
        ]);
        $someoneElses = TestAttempt::factory()->create([
            'user_id' => $this->learner(['username' => 'amine'])->id,
            'test_id' => $sitting->test_id,
            'submitted_at' => null,
        ]);

        $upload = fn (int $id) => $this->actingAs($learner)->postJson(route('learn.recordings.store'), [
            'audio' => UploadedFile::fake()->create('answer.wav', 50, 'audio/wav'),
            'recordable_type' => 'test_attempt',
            'recordable_id' => $id,
        ]);

        $upload($sitting->id)->assertCreated();
        $upload($someoneElses->id)->assertJsonValidationErrors('recordable_id');

        $sitting->forceFill(['submitted_at' => now()])->save();
        $upload($sitting->id)->assertJsonValidationErrors('recordable_id');

        $this->assertDatabaseCount('voice_recordings', 1);
    }

    public function test_a_block_the_learner_cannot_reach_is_refused()
    {
        $foreign = Block::factory()->ofType(BlockType::Practice)->create();

        $this->actingAs($this->learner())->postJson(route('learn.recordings.store'), [
            'audio' => UploadedFile::fake()->create('answer.wav', 50, 'audio/wav'),
            'recordable_type' => 'block',
            'recordable_id' => $foreign->id,
        ])->assertJsonValidationErrors('recordable_id');

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_a_speaking_answer_references_the_learners_own_recording()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $lesson = $this->publishedLesson([BlockType::Situation, BlockType::Practice]);
        $practice = $lesson->visibleBlocks()->get()->firstOrFail(fn (Block $block): bool => $block->type === BlockType::Practice);
        $activity = Activity::factory()->ofType(ActivityType::Speaking)->create();
        $placement = ActivityPlacement::factory()->in($practice)->create(['activity_id' => $activity->id]);

        $mine = MediaAsset::factory()->recording()->create(['uploaded_by' => $learner->id]);
        $theirs = MediaAsset::factory()->recording()->create(['uploaded_by' => User::factory()->employee()->create()->id]);

        $answerUrl = route('learn.lessons.activity.answer', ['lesson' => $lesson, 'block' => $practice, 'placement' => $placement]);

        $this->actingAs($learner)->post($answerUrl, [
            'answers' => ['i1' => ['recording_media_id' => $mine->id, 'duration_ms' => 5200]],
        ])->assertRedirect();

        $this->assertDatabaseHas('attempts', [
            'activity_id' => $activity->id,
            'response_media_id' => $mine->id,
            'is_correct' => null,
            'score' => null,
        ]);

        // Another learner's file cannot be claimed as the answer.
        $this->actingAs($learner)->post($answerUrl, [
            'answers' => ['i1' => ['recording_media_id' => $theirs->id, 'duration_ms' => 100]],
        ]);

        $this->assertDatabaseHas('attempts', ['attempt_no' => 2, 'response_media_id' => null]);
    }
}

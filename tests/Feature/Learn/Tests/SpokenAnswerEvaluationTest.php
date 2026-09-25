<?php

namespace Tests\Feature\Learn\Tests;

use App\Contracts\SpeakingEvaluation;
use App\Enums\ActivityType;
use App\Enums\AiFeature;
use App\Enums\GenerationStatus;
use App\Jobs\TranscribeAndEvaluateSpokenAnswer;
use App\Models\Activity;
use App\Models\Attempt;
use App\Models\MediaAsset;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Stt\FakeSpeechToTextProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * A recorded answer is transcribed and judged with a structured verdict
 * (TEST-07, DATA-02, AIE-01, AIE-04, PRIV-04; spec 0004). Fake providers,
 * sync queue, fake private disk.
 */
class SpokenAnswerEvaluationTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
        config([
            'services.ai.provider' => 'fake',
            'services.stt.provider' => 'fake',
            'services.tts.provider' => 'fake',
        ]);
        Storage::fake(MediaAsset::DISK_LOCAL);
        Storage::fake(MediaAsset::DISK_PUBLIC);
    }

    private function recordingFor(User $learner): MediaAsset
    {
        $asset = MediaAsset::factory()->recording()->create(['uploaded_by' => $learner->id]);
        Storage::disk(MediaAsset::DISK_LOCAL)->put($asset->path, 'webm-bytes');

        return $asset;
    }

    private function speakingTest(): Test
    {
        $test = Test::factory()->pre()->create([
            'department_id' => $this->department->id,
            'settings' => ['time_limit_seconds' => null, 'results_visibility' => 'hidden'],
        ]);
        $activity = Activity::factory()->ofType(ActivityType::Speaking)->create(['show_meaning_enabled' => false]);
        $test->questions()->create(['activity_id' => $activity->id, 'position' => 1]);

        return $test;
    }

    public function test_the_job_stores_the_transcript_and_a_structured_evaluation()
    {
        $learner = $this->learner();
        $test = $this->speakingTest();
        $activity = $test->questions()->firstOrFail()->activity()->firstOrFail();
        $asset = $this->recordingFor($learner);

        $sitting = TestAttempt::factory()->create(['user_id' => $learner->id, 'test_id' => $test->id]);
        $attempt = Attempt::factory()->create([
            'user_id' => $learner->id,
            'activity_id' => $activity->id,
            'activity_version_id' => $activity->currentVersion()->firstOrFail()->id,
            'test_attempt_id' => $sitting->id,
            'raw_answer' => ['i1' => ['recording_media_id' => $asset->id, 'duration_ms' => 5200]],
            'response_media_id' => $asset->id,
            'transcript' => null,
            'ai_status' => GenerationStatus::Pending,
            'is_correct' => null,
            'score' => null,
            'max_score' => null,
        ]);

        TranscribeAndEvaluateSpokenAnswer::dispatch($attempt->id);

        $attempt->refresh();
        $this->assertSame(FakeSpeechToTextProvider::TRANSCRIPT, $attempt->transcript);
        $this->assertSame(GenerationStatus::Done, $attempt->ai_status);
        $this->assertNotNull($attempt->ai_feedback);
        $this->assertSame(SpeakingEvaluation::CRITERIA, array_keys($attempt->ai_feedback['criteria']));

        foreach ($attempt->ai_feedback['criteria'] as $criterion) {
            $this->assertIsInt($criterion['score']);
            $this->assertIsString($criterion['comment']);
        }

        $this->assertNotSame('', $attempt->ai_feedback['better_answer']);
        // The mean of the four criterion scores (80, 70, 75, 85).
        $this->assertSame(77.5, (float) $attempt->score);

        // The recording stays on the private disk (PRIV-04).
        Storage::disk(MediaAsset::DISK_LOCAL)->assertExists($asset->path);

        $this->assertDatabaseHas('ai_usages', ['feature' => AiFeature::Stt->value, 'user_id' => $learner->id]);
        $this->assertDatabaseHas('ai_usages', ['feature' => AiFeature::SpeakingEval->value, 'user_id' => $learner->id]);

        // Idempotent: a second run neither re-transcribes nor re-bills.
        TranscribeAndEvaluateSpokenAnswer::dispatch($attempt->id);
        $this->assertDatabaseCount('ai_usages', 2);
    }

    public function test_finishing_a_test_transcribes_and_judges_the_spoken_answer()
    {
        $learner = $this->learner();
        $test = $this->speakingTest();
        $asset = $this->recordingFor($learner);

        $this->actingAs($learner)->post(route('learn.tests.start', $test))->assertRedirect();
        $sitting = TestAttempt::query()->firstOrFail();

        $this->actingAs($learner)->post(route('learn.tests.finish', ['test' => $test, 'attempt' => $sitting]), [
            'number' => 1,
            'answer' => ['i1' => ['recording_media_id' => $asset->id, 'duration_ms' => 4000]],
        ])->assertRedirect();

        $row = Attempt::query()->where('test_attempt_id', $sitting->id)->firstOrFail();
        $this->assertSame($asset->id, $row->response_media_id);
        $this->assertSame(FakeSpeechToTextProvider::TRANSCRIPT, $row->transcript);
        $this->assertSame(GenerationStatus::Done, $row->ai_status);
        $this->assertArrayHasKey('task_completion', $row->ai_feedback['criteria'] ?? []);
    }

    public function test_someone_elses_recording_is_never_attached_to_an_answer()
    {
        $learner = $this->learner();
        $other = User::factory()->employee()->create();
        $test = $this->speakingTest();
        $foreign = $this->recordingFor($other);

        $this->actingAs($learner)->post(route('learn.tests.start', $test));
        $sitting = TestAttempt::query()->firstOrFail();

        $this->actingAs($learner)->post(route('learn.tests.finish', ['test' => $test, 'attempt' => $sitting]), [
            'number' => 1,
            'answer' => ['i1' => ['recording_media_id' => $foreign->id, 'duration_ms' => 4000]],
        ])->assertRedirect();

        $row = Attempt::query()->where('test_attempt_id', $sitting->id)->firstOrFail();
        $this->assertNull($row->response_media_id);
        $this->assertNull($row->transcript);
    }

    public function test_a_missing_recording_ends_in_a_failed_state_with_a_reason()
    {
        $learner = $this->learner();
        $test = $this->speakingTest();
        $activity = $test->questions()->firstOrFail()->activity()->firstOrFail();
        $asset = MediaAsset::factory()->recording()->create(['uploaded_by' => $learner->id]);

        $attempt = Attempt::factory()->create([
            'user_id' => $learner->id,
            'activity_id' => $activity->id,
            'activity_version_id' => $activity->currentVersion()->firstOrFail()->id,
            'response_media_id' => $asset->id,
            'transcript' => null,
            'ai_status' => GenerationStatus::Pending,
        ]);

        try {
            TranscribeAndEvaluateSpokenAnswer::dispatch($attempt->id);
            $this->fail('A missing recording did not fail the job.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('missing', $e->getMessage());
        }

        $attempt->refresh();
        $this->assertSame(GenerationStatus::Failed, $attempt->ai_status);
        $this->assertStringContainsString('missing', (string) $attempt->ai_failed_reason);
        $this->assertDatabaseCount('ai_usages', 0);
    }
}

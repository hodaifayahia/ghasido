<?php

namespace Tests\Feature;

use App\Contracts\TtsProvider;
use App\Enums\AudioSpeed;
use App\Enums\GenerationStatus;
use App\Enums\MediaKind;
use App\Enums\MediaLibrary;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\ActivityVersion;
use App\Models\AiScenario;
use App\Models\AiUsage;
use App\Models\Attempt;
use App\Models\AudioClip;
use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\Block;
use App\Models\BlockCompletion;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\LexiconItem;
use App\Models\MediaAsset;
use App\Models\PhrasebookItem;
use App\Models\Reminder;
use App\Models\ReminderTemplate;
use App\Models\RoleplayAttempt;
use App\Models\SeatQuota;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\Unit;
use App\Models\User;
use App\Models\VoiceRecording;
use App\Services\Audio\AudioLibrary;
use App\Services\Tts\FakeTtsProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The integration gate for the spec 0003 foundation (Part J, lane
 * `schema-integrate`): every model the four schema lanes wrote can be created
 * through its own factory against the merged migrations, and the audio
 * pipeline (AudioLibrary → GenerateAudioClip → fake TTS → media_assets)
 * runs end to end on the sync queue (TTS-01, TTS-02, CTRL-05).
 *
 * A failure here means two lanes disagree about a column, a foreign key, a
 * morph name or a default, before any screen is built on top of them.
 */
class FoundationSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every model with a factory, in dependency order. Each entry creates
     * its own parents through nested factories, so the list is also a check
     * that every FK target the migrations name really exists.
     *
     * @return list<class-string<Model>>
     */
    private static function factoryModels(): array
    {
        return [
            // Tenancy (spec 0001, 0002)
            Hotel::class,
            Department::class,
            SeatQuota::class,
            User::class,
            // Media and audio (B.3, B.4)
            MediaAsset::class,
            AudioClip::class,
            // Content (B.5)
            Course::class,
            Unit::class,
            Lesson::class,
            Block::class,
            LexiconItem::class,
            Activity::class,
            ActivityVersion::class,
            ActivityPlacement::class,
            AiScenario::class,
            // Learner data (B.6)
            BlockCompletion::class,
            LessonCompletion::class,
            Test::class,
            TestAttempt::class,
            Attempt::class,
            RoleplayAttempt::class,
            PhrasebookItem::class,
            VoiceRecording::class,
            Certificate::class,
            AiUsage::class,
            // Messaging (B.7)
            ReminderTemplate::class,
            AutomationRule::class,
            Reminder::class,
        ];
    }

    public function test_every_model_can_be_created_through_its_factory()
    {
        foreach (self::factoryModels() as $class) {
            /** @var Model $row */
            $row = $class::factory()->create();

            $this->assertTrue($row->exists, "{$class} factory did not persist a row.");
            $this->assertNotNull(
                $row->fresh(),
                "{$class} row {$row->getKey()} could not be read back from {$row->getTable()}.",
            );
        }

        // AuditLog has no factory by design: it is only ever written through
        // record() (SEC-06, ADM-03), so that path is exercised instead.
        $hotel = Hotel::factory()->create();
        $hotel->name = 'Renamed for the smoke test';
        $log = AuditLog::record($hotel, 'hotels.updated');

        $this->assertNotNull($log->fresh());
        $this->assertSame('hotels.updated', $log->action);
    }

    public function test_creating_an_activity_writes_its_first_version()
    {
        $activity = Activity::factory()->create();

        $this->assertSame(1, $activity->fresh()?->current_version);
        $this->assertDatabaseHas('activity_versions', [
            'activity_id' => $activity->id,
            'version' => 1,
        ]);
    }

    public function test_the_fake_tts_generates_a_clip_end_to_end_on_the_sync_queue()
    {
        Storage::fake(MediaAsset::DISK_PUBLIC);

        // The test environment does not set TTS_PROVIDER, so the container
        // must fall back to the fake provider (Part C, API-04).
        $this->assertInstanceOf(FakeTtsProvider::class, app(TtsProvider::class));

        $library = app(AudioLibrary::class);

        // QUEUE_CONNECTION=sync in phpunit.xml: dispatch() runs the job
        // inline, so the clip is generated before ensure() returns.
        $clips = $library->ensureBoth('How can I help you?');

        foreach ([AudioSpeed::Normal, AudioSpeed::Slow] as $speed) {
            $clip = $clips[$speed->value]->fresh();

            $this->assertNotNull($clip);
            $this->assertSame(GenerationStatus::Done, $clip->status);
            $this->assertSame('fake', $clip->provider);
            $this->assertNotNull($clip->generated_at);
            $this->assertNotNull($clip->media_asset_id, "{$speed->value} clip has no media asset.");

            $asset = $clip->mediaAsset;

            $this->assertNotNull($asset);
            $this->assertSame(MediaAsset::DISK_PUBLIC, $asset->disk);
            $this->assertSame(MediaKind::Audio, $asset->kind);
            $this->assertSame(MediaLibrary::Generated, $asset->library);
            $this->assertSame('audio/wav', $asset->mime);
            $this->assertGreaterThan(0, $asset->size_bytes);
            $this->assertMatchesRegularExpression('#^content/audio/\d{4}/\d{2}/[0-9a-f-]{36}\.wav$#', $asset->path);

            Storage::disk(MediaAsset::DISK_PUBLIC)->assertExists($asset->path);

            $this->assertNotNull($clip->url());
            $this->assertSame($clip->url(), $library->urlFor('How can I help you?', $speed));
        }

        // Normal and slow are distinct rows and distinct files (TTS-01).
        $this->assertSame(2, AudioClip::query()->count());
        $this->assertSame(2, MediaAsset::query()->count());
        $this->assertNotSame(
            $clips['normal']->fresh()?->media_asset_id,
            $clips['slow']->fresh()?->media_asset_id,
        );

        // Ensuring the same sentence again is a no-op: no third row, no
        // second billed call (TTS-02).
        $library->ensureBoth('How can I help you?');

        $this->assertSame(2, AudioClip::query()->count());
        $this->assertSame(2, MediaAsset::query()->count());
    }
}

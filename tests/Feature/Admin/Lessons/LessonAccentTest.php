<?php

namespace Tests\Feature\Admin\Lessons;

use App\Enums\Accent;
use App\Enums\AudioSpeed;
use App\Enums\BlockType;
use App\Jobs\GenerateAudioClip;
use App\Jobs\GeneratePronunciationGuide;
use App\Models\AudioClip;
use App\Models\Block;
use App\Models\Course;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\PronunciationGuide;
use App\Models\TtsSetting;
use App\Models\Unit;
use App\Services\Audio\AudioLibrary;
use App\Services\Content\BlockShaper;
use App\Services\Learning\BlockPresenter;
use App\Services\Tts\TtsSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A lesson is taught in British or American English (spec 0006 §3): its
 * audio is rendered in that accent's voice and its speakable texts get a
 * pronunciation guide in that accent. A lesson with no accent keeps the
 * platform voice, so existing audio is untouched.
 */
class LessonAccentTest extends TestCase
{
    use BuildsContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tts.voice' => 'flux-hannah-en']);
        $this->buildContent();
    }

    public function test_each_accent_has_its_own_voice_with_sensible_fallbacks()
    {
        $settings = app(TtsSettings::class);

        // The platform voice (Hannah) is American, so it serves American
        // lessons; British lessons fall back to the British default.
        $this->assertSame(Accent::American, $settings->defaultAccent());
        $this->assertSame('flux-hannah-en', $settings->voiceFor(Accent::American));
        $this->assertSame(Accent::British->defaultVoice(), $settings->voiceFor(Accent::British));

        TtsSetting::query()->create(['british_voice' => 'flux-colin-en', 'american_voice' => 'flux-miles-en']);

        $this->assertSame('flux-colin-en', $settings->voiceFor(Accent::British));
        $this->assertSame('flux-miles-en', $settings->voiceFor(Accent::American));

        // A voice of the wrong accent is never used for an accent.
        TtsSetting::query()->update(['british_voice' => 'flux-miles-en']);
        $this->assertSame(Accent::British->defaultVoice(), $settings->voiceFor(Accent::British));
    }

    public function test_switching_a_lesson_to_british_queues_british_audio_and_guides()
    {
        Queue::fake();

        $this->actingAs($this->owner)
            ->patch(route('lessons.update', $this->lesson), ['accent' => 'en-GB'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(Accent::British, $this->lesson->refresh()->accent);

        $british = Accent::British->defaultVoice();
        $this->assertDatabaseHas('audio_clips', [
            'text_hash' => AudioClip::hashFor('How can I help you?'),
            'voice' => $british,
            'speed' => AudioSpeed::Slow->value,
        ]);
        $this->assertDatabaseHas('pronunciation_guides', [
            'text_hash' => AudioClip::hashFor('How can I help you?'),
            'accent' => 'en-GB',
        ]);

        Queue::assertPushed(GenerateAudioClip::class);
        Queue::assertPushed(GeneratePronunciationGuide::class, PronunciationGuide::query()->count());
    }

    public function test_an_unknown_accent_is_refused()
    {
        $this->actingAs($this->owner)
            ->patch(route('lessons.update', $this->lesson), ['accent' => 'fr-FR'])
            ->assertSessionHasErrors('accent');
    }

    public function test_the_builder_and_the_step_read_audio_in_the_lessons_accent()
    {
        $this->lesson->forceFill(['accent' => Accent::British])->save();
        $british = AudioClip::factory()->done()->create([
            'text' => 'How can I help you?',
            'voice' => Accent::British->defaultVoice(),
        ]);
        // The same sentence in the platform voice must not be served.
        AudioClip::factory()->done()->create(['text' => 'How can I help you?', 'voice' => 'flux-hannah-en']);

        $rows = app(BlockShaper::class)->forLesson($this->lesson);
        $listen = collect($rows)->firstWhere('type', BlockType::ListenRepeat->value);
        $this->assertSame($british->url(), $listen['audio']['How can I help you?']['normal']['url']);

        $this->assertSame(
            $british->url(),
            app(AudioLibrary::class)->urlsFor(['How can I help you?'], Accent::British)['How can I help you?']['normal'],
        );
    }

    public function test_a_lesson_without_an_accent_keeps_the_platform_voice()
    {
        $platform = AudioClip::factory()->done()->create(['text' => 'How can I help you?', 'voice' => 'flux-hannah-en']);

        $rows = app(BlockShaper::class)->forLesson($this->lesson);
        $listen = collect($rows)->firstWhere('type', BlockType::ListenRepeat->value);

        $this->assertNull($this->lesson->accent);
        $this->assertSame(Accent::American, $this->lesson->speakingAccent());
        $this->assertSame($platform->url(), $listen['audio']['How can I help you?']['normal']['url']);
    }

    public function test_generate_audio_for_a_lesson_uses_its_accent_and_queues_guides()
    {
        Queue::fake();
        $this->lesson->forceFill(['accent' => Accent::British])->save();

        $this->actingAs($this->owner)
            ->post(route('audio.generate'), ['texts' => ['How can I help you?'], 'lesson_id' => $this->lesson->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audio_clips', ['voice' => Accent::British->defaultVoice(), 'text_hash' => AudioClip::hashFor('How can I help you?')]);
        $this->assertDatabaseHas('pronunciation_guides', ['accent' => 'en-GB']);
    }

    public function test_generate_audio_for_another_hotels_lesson_is_forbidden_to_a_manager()
    {
        $other = Hotel::factory()->create();
        $course = Course::factory()->forDepartment($this->department->id)->forHotel($other->id)->create();
        $unit = Unit::factory()->create(['course_id' => $course->id]);
        $foreign = Lesson::factory()->create(['unit_id' => $unit->id]);

        $this->actingAs($this->manager())
            ->post(route('audio.generate'), ['texts' => ['Hello'], 'lesson_id' => $foreign->id])
            ->assertForbidden();
    }

    public function test_voice_settings_take_one_voice_per_accent_of_that_accent()
    {
        $this->actingAs($this->owner)
            ->post(route('tts.settings.update'), [
                'voice' => 'flux-hannah-en',
                'expressivity' => 0,
                'british_voice' => 'flux-colin-en',
                'american_voice' => 'flux-miles-en',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $setting = TtsSetting::query()->sole();
        $this->assertSame('flux-colin-en', $setting->british_voice);
        $this->assertSame('flux-miles-en', $setting->american_voice);

        $this->actingAs($this->owner)
            ->post(route('tts.settings.update'), [
                'voice' => 'flux-hannah-en',
                'expressivity' => 0,
                'british_voice' => 'flux-miles-en',
            ])
            ->assertSessionHasErrors('british_voice');
    }

    public function test_the_step_page_tells_the_learner_page_the_judging_accent()
    {
        $this->lesson->forceFill(['accent' => Accent::British])->save();
        $block = Block::query()->where('lesson_id', $this->lesson->id)->where('type', BlockType::ListenRepeat->value)->firstOrFail();

        $presented = app(BlockPresenter::class)->present($block, $this->owner);

        $this->assertSame('en-GB', $presented['accent']);
    }
}

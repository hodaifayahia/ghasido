<?php

namespace Tests\Feature\Admin\Lessons;

use App\Enums\AudioSpeed;
use App\Enums\BlockType;
use App\Enums\GenerationStatus;
use App\Models\AudioClip;
use App\Models\AuditLog;
use App\Models\LexiconItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Words and expressions: create into a block, reuse, AI draft through the
 * fake provider, apply, and audio clips (CMS-06, CTRL-03, GEN-01, GEN-03,
 * GEN-04, TTS-01..03, TTS-06). phpunit.xml runs the queue synchronously.
 */
class LexiconTest extends TestCase
{
    use BuildsContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildContent();
        Storage::fake('public');
    }

    public function test_a_new_word_is_created_and_appended_to_the_vocabulary_block()
    {
        $vocabulary = $this->lesson->blocks()->where('type', BlockType::Vocabulary->value)->firstOrFail();

        $this->actingAs($this->owner)
            ->post(route('lexicon.store'), [
                'kind' => 'word',
                'english_text' => 'Towel',
                'part_of_speech' => 'n',
                'block_id' => $vocabulary->id,
                'show_meaning_enabled' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item = LexiconItem::query()->where('english_text', 'Towel')->firstOrFail();

        $this->assertSame('manual', $item->source);
        $this->assertFalse($item->show_meaning_enabled);
        $this->assertSame([$item->id], $vocabulary->lexiconItems()->pluck('lexicon_items.id')->all());
        $this->assertSame(1, AuditLog::query()->where('action', 'lexicon.created')->count());
    }

    public function test_existing_items_can_be_searched_to_reuse()
    {
        LexiconItem::factory()->create(['english_text' => 'Pillow']);
        LexiconItem::factory()->create(['english_text' => 'Passport']);
        LexiconItem::factory()->expression()->create(['english_text' => 'Please follow me.']);

        $this->actingAs($this->owner)
            ->getJson(route('lexicon.index', ['search' => 'p', 'kind' => 'word']))
            ->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.englishText', 'Passport')
            ->assertJsonPath('items.0.missingAudio', true);
    }

    public function test_generate_writes_a_draft_through_the_fake_provider_and_apply_copies_it()
    {
        $item = LexiconItem::factory()->withoutMeaning()->create(['english_text' => 'Towel', 'created_by' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->post(route('lexicon.generate', $item))
            ->assertRedirect();

        $item->refresh();

        // The draft is stored, the live columns are untouched (GEN-03).
        $this->assertSame(GenerationStatus::Done, $item->ai_status);
        $this->assertNotNull($item->ai_draft);
        $this->assertNull($item->arabic_meaning);

        $this->actingAs($this->owner)
            ->post(route('lexicon.apply-draft', $item), ['simple_explanation' => 'Edited before applying.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item->refresh();

        $this->assertSame('ai', $item->source);
        $this->assertNotNull($item->arabic_meaning);
        $this->assertSame('Edited before applying.', $item->simple_explanation);
        $this->assertNull($item->ai_draft);
        $this->assertSame(1, AuditLog::query()->where('action', 'lexicon.draft_applied')->count());
    }

    public function test_generate_audio_creates_a_normal_and_a_slow_clip()
    {
        $item = LexiconItem::factory()->create(['english_text' => 'Room key', 'hotel_example' => 'Here is your room key.']);

        $this->actingAs($this->owner)
            ->post(route('lexicon.audio', $item))
            ->assertRedirect();

        $clips = AudioClip::query()->where('text_hash', AudioClip::hashFor('Room key'))->get();

        $this->assertCount(2, $clips);
        $this->assertEqualsCanonicalizing([AudioSpeed::Normal, AudioSpeed::Slow], $clips->pluck('speed')->all());
        $this->assertTrue($clips->every(fn (AudioClip $clip): bool => $clip->isDone()));
        $this->assertSame(2, AudioClip::query()->where('text_hash', AudioClip::hashFor('Here is your room key.'))->count());

        $this->actingAs($this->owner)
            ->getJson(route('lexicon.index', ['search' => 'Room key']))
            ->assertJsonPath('items.0.missingAudio', false)
            ->assertJsonPath('items.0.audio.Room key.slow.status', 'done');
    }

    public function test_updating_an_item_changes_its_columns()
    {
        $item = LexiconItem::factory()->create(['english_text' => 'Towel']);

        $this->actingAs($this->owner)
            ->patch(route('lexicon.update', $item), ['arabic_meaning' => 'منشفة', 'ipa' => '/ˈtaʊəl/'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('/ˈtaʊəl/', $item->fresh()?->ipa);
    }
}

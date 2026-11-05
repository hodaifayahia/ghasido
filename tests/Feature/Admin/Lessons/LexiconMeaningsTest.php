<?php

namespace Tests\Feature\Admin\Lessons;

use App\Enums\BlockType;
use App\Enums\GenerationStatus;
use App\Models\LexiconItem;
use App\Models\TextTranslation;
use App\Services\Meaning\MeaningTexts;
use App\Services\Meaning\MeaningTranslations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The word and expression editor without fixed Arabic fields (client
 * request 2026-11-05): the meanings of the English word and of its hotel
 * example are Show Meaning translations kept per text, written with
 * Translate meaning or drafted by the AI; a hand-written one is never
 * replaced (CTRL-03, GEN-03).
 */
class LexiconMeaningsTest extends TestCase
{
    use BuildsContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildContent();
    }

    private function stored(string $text): ?TextTranslation
    {
        return TextTranslation::query()->where('hash', TextTranslation::hashOf($text))->where('locale', 'ar')->first();
    }

    public function test_a_word_saved_without_arabic_keeps_its_old_columns_untouched()
    {
        $item = LexiconItem::factory()->create(['english_text' => 'Towel', 'arabic_meaning' => 'منشفة']);

        $this->actingAs($this->owner)
            ->patch(route('lexicon.update', $item), ['simple_explanation' => 'A cloth to dry yourself.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('منشفة', $item->fresh()?->arabic_meaning);
        $this->assertSame('منشفة', $this->stored('Towel')?->translation);
    }

    public function test_renaming_a_word_does_not_hand_its_old_arabic_to_the_new_english()
    {
        $item = LexiconItem::factory()->create([
            'english_text' => 'Towel',
            'arabic_meaning' => 'منشفة',
            'hotel_example' => 'Here is a towel.',
            'hotel_example_arabic' => 'هذه منشفة.',
        ]);

        $this->actingAs($this->owner)
            ->patch(route('lexicon.update', $item), ['english_text' => 'Pillow', 'hotel_example' => 'Here is a pillow.'])
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertNull($item->arabic_meaning);
        $this->assertNull($item->hotel_example_arabic);
        $this->assertNull($this->stored('Pillow'));
        $this->assertNull($this->stored('Here is a pillow.'));
        // The old text keeps its meaning (DATA-10).
        $this->assertSame('منشفة', $this->stored('Towel')?->translation);
    }

    public function test_an_applied_ai_draft_never_replaces_a_meaning_written_by_hand()
    {
        $item = LexiconItem::factory()->withoutMeaning()->create(['english_text' => 'Towel', 'created_by' => $this->owner->id]);
        TextTranslation::query()->create([
            'hash' => TextTranslation::hashOf('Towel'),
            'locale' => 'ar',
            'source_text' => 'Towel',
            'translation' => 'فوطة',
            'status' => GenerationStatus::Done,
            'source' => MeaningTranslations::SOURCE_MANUAL,
        ]);

        $this->actingAs($this->owner)->post(route('lexicon.generate', $item))->assertRedirect();
        $this->actingAs($this->owner)
            ->post(route('lexicon.apply-draft', $item), ['hotel_example' => 'Here is your towel.', 'hotel_example_arabic' => 'هذه منشفتك.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('فوطة', $this->stored('Towel')?->translation);
        $this->assertSame('هذه منشفتك.', $this->stored('Here is your towel.')?->translation);
        $this->assertSame(MeaningTranslations::SOURCE_AI, $this->stored('Here is your towel.')?->source);
        $this->assertNull($item->fresh()?->hotel_example_arabic);
    }

    public function test_a_lessons_words_and_examples_are_texts_to_translate()
    {
        $vocabulary = $this->lesson->blocks()->where('type', BlockType::Vocabulary->value)->firstOrFail();
        $item = LexiconItem::factory()->withoutMeaning()->create(['english_text' => 'Key card', 'hotel_example' => 'Here is your key card.']);
        $vocabulary->lexiconItems()->attach($item->id, ['position' => 1]);

        $texts = MeaningTexts::forLesson($this->lesson->fresh() ?? $this->lesson);

        $this->assertContains('Key card', $texts);
        $this->assertContains('Here is your key card.', $texts);
    }
}

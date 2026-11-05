<?php

namespace Tests\Feature\Learn;

use App\Enums\BlockType;
use App\Enums\GenerationStatus;
use App\Models\Block;
use App\Models\LexiconItem;
use App\Models\PhrasebookItem;
use App\Models\TextTranslation;
use App\Services\Meaning\MeaningTranslations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * "Translate meaning" instead of fixed Arabic fields (client request
 * 2026-11-05): the meanings of words, expressions, Listen & Repeat
 * sentences, dialogue lines and the video example are Show Meaning
 * translations of their English text, in any helper language, revealed
 * only after the tap (CTRL-01..03). Old inline Arabic is never lost: a data
 * migration and the save hooks copy it into `text_translations`.
 */
class LessonStepMeaningsTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    private const string MIGRATION = 'database/migrations/2026_11_05_100000_copy_inline_arabic_meanings_to_text_translations.php';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    private function translated(string $text, string $locale, string $meaning, string $source = MeaningTranslations::SOURCE_MANUAL): void
    {
        TextTranslation::query()->create([
            'hash' => TextTranslation::hashOf($text),
            'locale' => $locale,
            'source_text' => $text,
            'translation' => $meaning,
            'status' => GenerationStatus::Done,
            'source' => $source,
        ]);
    }

    private function stored(string $text, string $locale = 'ar'): ?TextTranslation
    {
        return TextTranslation::query()->where('hash', TextTranslation::hashOf($text))->where('locale', $locale)->first();
    }

    public function test_a_word_without_the_old_arabic_column_still_offers_show_meaning_in_the_learners_language()
    {
        $learner = $this->learner(['meaning_locale' => 'fr']);
        $this->submitPreTest($learner);
        $lesson = $this->publishedLesson([BlockType::Vocabulary, BlockType::Complete]);
        $block = $lesson->visibleBlocks()->firstOrFail();

        $item = LexiconItem::factory()->withoutMeaning()->create([
            'english_text' => 'Towel',
            'simple_explanation' => 'A cloth to dry yourself.',
            'hotel_example' => 'Here is a fresh towel.',
        ]);
        $block->lexiconItems()->attach($item->id, ['position' => 1]);

        $this->translated('Towel', 'fr', 'Serviette');
        $this->translated('Here is a fresh towel.', 'fr', 'Voici une serviette propre.');

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $block]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('block.lexicon.0.showMeaning', true)
                ->where('block.lexicon.0.meaning', ['explanation' => 'A cloth to dry yourself.'])
            );

        $this->actingAs($learner)->postJson(route('meaning'), ['text' => 'Towel'])
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('text', 'Serviette')
            ->assertJsonPath('locale', 'fr');

        $this->actingAs($learner)->postJson(route('meaning'), ['text' => 'Here is a fresh towel.'])
            ->assertJsonPath('text', 'Voici une serviette propre.');
    }

    public function test_a_word_with_show_meaning_switched_off_offers_none()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $lesson = $this->publishedLesson([BlockType::Vocabulary, BlockType::Complete]);
        $block = $lesson->visibleBlocks()->firstOrFail();
        $item = LexiconItem::factory()->showMeaningDisabled()->create(['english_text' => 'Lobby']);
        $block->lexiconItems()->attach($item->id, ['position' => 1]);

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $block]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('block.lexicon.0.showMeaning', false)
                ->where('block.lexicon.0.meaning', null)
            );
    }

    public function test_sentences_lines_and_the_video_example_ship_without_their_old_arabic()
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);
        $lesson = $this->publishedLesson([BlockType::Situation]);

        $listen = Block::factory()->ofType(BlockType::ListenRepeat)->at(2)->create([
            'lesson_id' => $lesson->id,
            'settings' => ['items' => [['text' => 'How can I help you?', 'arabic' => 'كيف يمكنني مساعدتك؟', 'image' => null]]],
        ]);
        $dialogue = Block::factory()->ofType(BlockType::Dialogue)->at(3)->create([
            'lesson_id' => $lesson->id,
            'settings' => ['lines' => [['speaker' => 'guest', 'text' => 'I have a reservation.', 'arabic' => 'لدي حجز.']]],
        ]);
        $video = Block::factory()->ofType(BlockType::Video)->at(4)->create([
            'lesson_id' => $lesson->id,
            'settings' => ['example' => ['text' => 'Enjoy your stay.', 'arabic' => 'إقامة سعيدة', 'note' => 'From the video.']],
        ]);

        $step = fn (Block $block) => $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $block]))
            ->assertOk();

        $step($listen)->assertInertia(fn (Assert $page) => $page
            ->where('block.settings.items.0.text', 'How can I help you?')
            ->missing('block.settings.items.0.arabic'));
        $step($dialogue)->assertInertia(fn (Assert $page) => $page
            ->where('block.settings.lines.0.text', 'I have a reservation.')
            ->missing('block.settings.lines.0.arabic'));
        $step($video)->assertInertia(fn (Assert $page) => $page
            ->where('block.settings.example.text', 'Enjoy your stay.')
            ->missing('block.settings.example.arabic'));

        // The Arabic written on the blocks is what Show Meaning returns now.
        $this->actingAs($learner)->postJson(route('meaning'), ['text' => 'I have a reservation.'])
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('text', 'لدي حجز.');
    }

    public function test_inline_arabic_fills_only_the_gaps_of_the_per_text_store()
    {
        $this->translated('Welcome to the hotel.', 'ar', 'نص يدوي');
        $this->translated('Good morning.', 'ar', 'مسودة', MeaningTranslations::SOURCE_AI);
        TextTranslation::query()->create([
            'hash' => TextTranslation::hashOf('See you soon.'),
            'locale' => 'ar',
            'source_text' => 'See you soon.',
            'status' => GenerationStatus::Pending,
            'source' => MeaningTranslations::SOURCE_REQUESTED,
        ]);

        $lesson = $this->publishedLesson([BlockType::Situation]);
        Block::factory()->ofType(BlockType::ListenRepeat)->at(2)->create([
            'lesson_id' => $lesson->id,
            'settings' => ['items' => [
                ['text' => 'Welcome to the hotel.', 'arabic' => 'مرحباً'],
                ['text' => 'Good morning.', 'arabic' => 'صباح الخير'],
                ['text' => 'See you soon.', 'arabic' => 'أراك قريباً'],
                ['text' => 'Thank you.', 'arabic' => 'شكراً'],
            ]],
        ]);

        // A hand-written and a finished AI translation are kept as they are.
        $this->assertSame('نص يدوي', $this->stored('Welcome to the hotel.')?->translation);
        $this->assertSame('مسودة', $this->stored('Good morning.')?->translation);
        // A learner's open request and an untranslated text are filled.
        $this->assertSame('أراك قريباً', $this->stored('See you soon.')?->translation);
        $this->assertSame(GenerationStatus::Done, $this->stored('See you soon.')?->status);
        $this->assertSame(MeaningTranslations::SOURCE_MANUAL, $this->stored('Thank you.')?->source);
    }

    public function test_the_phrasebook_shows_a_saved_words_stored_meaning()
    {
        $learner = $this->learner();
        $item = LexiconItem::factory()->withoutMeaning()->create(['english_text' => 'Pillow']);
        $this->translated('Pillow', 'ar', 'وسادة');
        PhrasebookItem::factory()->create(['user_id' => $learner->id, 'lexicon_item_id' => $item->id]);

        $this->actingAs($learner)
            ->get(route('learn.phrasebook'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.0.showMeaning', true)
                ->where('items.0.meaning.arabic', 'وسادة'));
    }

    public function test_the_data_migration_copies_old_arabic_as_hand_written_meanings_once()
    {
        $lesson = $this->publishedLesson([BlockType::Situation]);
        LexiconItem::factory()->create([
            'english_text' => 'Luggage',
            'arabic_meaning' => 'أمتعة',
            'hotel_example' => 'May I take your   luggage?',
            'hotel_example_arabic' => 'هل آخذ أمتعتك؟',
        ]);
        Block::factory()->ofType(BlockType::Dialogue)->at(2)->create([
            'lesson_id' => $lesson->id,
            'settings' => ['lines' => [['speaker' => 'staff', 'text' => 'Welcome!', 'arabic' => 'أهلاً']]],
        ]);
        Block::factory()->ofType(BlockType::Video)->at(3)->create([
            'lesson_id' => $lesson->id,
            'settings' => ['example' => ['text' => 'Here is your key.', 'arabic' => 'هذا مفتاحك']],
        ]);
        Block::factory()->ofType(BlockType::ListenRepeat)->at(4)->create([
            'lesson_id' => $lesson->id,
            'settings' => ['items' => [['text' => 'One moment, please.', 'arabic' => 'لحظة من فضلك']]],
        ]);

        // As before the change: nothing in the store yet, except a manual
        // row (kept) and an AI draft (the lesson's Arabic wins over it).
        DB::table('text_translations')->delete();
        $this->translated('Luggage', 'ar', 'يدوي');
        $this->translated('Welcome!', 'ar', 'مسودة', MeaningTranslations::SOURCE_AI);

        $migration = require base_path(self::MIGRATION);
        $migration->up();

        $this->assertSame('يدوي', $this->stored('Luggage')?->translation);
        $this->assertSame('هل آخذ أمتعتك؟', $this->stored('May I take your luggage?')?->translation);
        $this->assertSame('أهلاً', $this->stored('Welcome!')?->translation);
        $this->assertSame(MeaningTranslations::SOURCE_MANUAL, $this->stored('Welcome!')?->source);
        $this->assertSame('هذا مفتاحك', $this->stored('Here is your key.')?->translation);
        $this->assertSame('لحظة من فضلك', $this->stored('One moment, please.')?->translation);
        $this->assertSame(GenerationStatus::Done, $this->stored('One moment, please.')?->status);

        $count = TextTranslation::query()->count();
        $migration->up();
        $this->assertSame($count, TextTranslation::query()->count());

        // The old columns keep their values (DATA-10).
        $this->assertSame('أمتعة', LexiconItem::query()->where('english_text', 'Luggage')->value('arabic_meaning'));
    }
}

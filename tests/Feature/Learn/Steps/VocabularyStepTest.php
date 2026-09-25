<?php

namespace Tests\Feature\Learn\Steps;

use App\Enums\BlockType;
use App\Models\Block;
use App\Models\LexiconItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * The Vocabulary step payload (LESSON-06, CTRL-01..03, PHRASE-02; spec 0003
 * Part E, photo_2). Words come from block_lexicon_item, each with its audio
 * pair and its Show Meaning content; exactly one is the featured word.
 */
class VocabularyStepTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    public function test_the_step_ships_its_words_with_audio_and_one_featured(): void
    {
        $learner = $this->learner();
        $this->submitPreTest($learner);

        $lesson = $this->publishedLesson([BlockType::Vocabulary, BlockType::Complete]);
        /** @var Block $block */
        $block = $lesson->visibleBlocks()->firstOrFail();

        $featured = LexiconItem::factory()->create([
            'english_text' => 'Air conditioning',
            'arabic_meaning' => 'تكييف الهواء',
        ]);
        $other = LexiconItem::factory()->create(['english_text' => 'Temperature']);
        $block->lexiconItems()->attach([
            $featured->id => ['position' => 1],
            $other->id => ['position' => 2],
        ]);
        $block->update([
            'settings' => array_merge($block->settings ?? [], [
                'featured_lexicon_item_id' => $featured->id,
            ]),
        ]);

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $block]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('block.type', 'vocabulary')
                ->has('block.lexicon', 2)
                ->where('block.lexicon.0.text', 'Air conditioning')
                ->where('block.lexicon.0.featured', true)
                ->where('block.lexicon.1.featured', false)
                ->has('block.lexicon.0.audio')
                ->has('block.lexicon.0.audio.normal')
                ->has('block.lexicon.0.audio.slow')
            );
    }

    public function test_arabic_is_present_in_the_lesson_payload_and_the_ui_gates_it(): void
    {
        // A lesson page ships the Arabic (Show Meaning is a UI reveal here,
        // CTRL-02); a test page carries none at all (CTRL-04, TEST-03).
        $learner = $this->learner();
        $this->submitPreTest($learner);

        $lesson = $this->publishedLesson([BlockType::Vocabulary, BlockType::Complete]);
        $block = $lesson->visibleBlocks()->firstOrFail();

        $item = LexiconItem::factory()->create([
            'english_text' => 'Reservation',
            'arabic_meaning' => 'حجز',
        ]);
        $block->lexiconItems()->attach($item->id, ['position' => 1]);

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $block]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('block.lexicon.0.showMeaning', true)
                ->where('block.lexicon.0.meaning.arabic', 'حجز')
            );
    }
}

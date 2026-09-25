<?php

namespace Tests\Feature\Learn;

use App\Models\Hotel;
use App\Models\LexiconItem;
use App\Models\PhrasebookItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * My Phrasebook (PHRASE-01..05, DATA-09; spec 0003 Part E).
 */
class PhrasebookTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    public function test_saving_a_lexicon_item_is_idempotent_and_answers_json_when_asked()
    {
        $learner = $this->learner();
        $item = LexiconItem::factory()->create();

        $this->actingAs($learner)
            ->postJson(route('learn.phrasebook.store'), ['lexicon_item_id' => $item->id])
            ->assertCreated()
            ->assertJson(['saved' => true, 'created' => true]);

        $this->actingAs($learner)
            ->postJson(route('learn.phrasebook.store'), ['lexicon_item_id' => $item->id])
            ->assertOk()
            ->assertJson(['saved' => true, 'created' => false]);

        $this->assertDatabaseCount('phrasebook_items', 1);
        $this->assertDatabaseHas('phrasebook_items', ['user_id' => $learner->id, 'lexicon_item_id' => $item->id]);
    }

    public function test_a_custom_phrase_is_deduplicated_on_its_normalised_text()
    {
        $learner = $this->learner();

        $this->actingAs($learner)->post(route('learn.phrasebook.store'), ['text' => 'How can I help you?', 'arabic' => 'كيف يمكنني مساعدتك؟'])
            ->assertRedirect();
        $this->actingAs($learner)->post(route('learn.phrasebook.store'), ['text' => '  how can  I help you? '])
            ->assertRedirect();

        $this->assertDatabaseCount('phrasebook_items', 1);
        $this->assertDatabaseHas('phrasebook_items', [
            'user_id' => $learner->id,
            'custom_text' => 'How can I help you?',
            'custom_arabic' => 'كيف يمكنني مساعدتك؟',
            'custom_hash' => PhrasebookItem::hashFor('How can I help you?'),
        ]);
    }

    public function test_either_an_item_id_or_a_text_is_required()
    {
        $this->actingAs($this->learner())
            ->postJson(route('learn.phrasebook.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lexicon_item_id', 'text']);
    }

    public function test_the_page_lists_the_learners_own_items_newest_first()
    {
        $learner = $this->learner();
        $item = LexiconItem::factory()->create(['english_text' => 'Air conditioning', 'arabic_meaning' => 'تكييف الهواء']);
        $older = PhrasebookItem::factory()->create(['user_id' => $learner->id, 'lexicon_item_id' => $item->id, 'saved_at' => now()->subDay()]);
        $newer = PhrasebookItem::factory()->create(['user_id' => $learner->id, 'lexicon_item_id' => null, 'custom_text' => 'Let me check that for you.', 'custom_hash' => PhrasebookItem::hashFor('Let me check that for you.'), 'saved_at' => now()]);
        PhrasebookItem::factory()->create(['user_id' => $this->learner(['username' => 'amine'])->id, 'lexicon_item_id' => $item->id]);

        $this->actingAs($learner)
            ->get(route('learn.phrasebook'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employee/Phrasebook')
                ->has('items', 2)
                ->where('items.0.id', $newer->id)
                ->where('items.0.source', 'custom')
                ->where('items.0.text', 'Let me check that for you.')
                ->where('items.1.id', $older->id)
                ->where('items.1.source', 'lexicon')
                ->where('items.1.text', 'Air conditioning')
                ->where('items.1.meaning.arabic', 'تكييف الهواء')
                ->where('items.1.removeUrl', route('learn.phrasebook.destroy', ['item' => $older]))
            );
    }

    public function test_removing_is_owner_only()
    {
        $learner = $this->learner();
        $stranger = $this->learner(['username' => 'amine']);
        $mine = PhrasebookItem::factory()->create(['user_id' => $learner->id]);
        $theirs = PhrasebookItem::factory()->create(['user_id' => $stranger->id]);

        $this->actingAs($learner)
            ->delete(route('learn.phrasebook.destroy', ['item' => $theirs]))
            ->assertForbidden();

        $this->actingAs($learner)
            ->deleteJson(route('learn.phrasebook.destroy', ['item' => $mine]))
            ->assertOk()
            ->assertJson(['saved' => false]);

        $this->assertDatabaseMissing('phrasebook_items', ['id' => $mine->id]);
        $this->assertDatabaseHas('phrasebook_items', ['id' => $theirs->id]);
    }

    public function test_another_hotels_lexicon_item_cannot_be_saved()
    {
        // Its Arabic would otherwise render on this learner's phrasebook
        // (ROLE-02, CMS-04; spec 0005 §1.4).
        $foreign = LexiconItem::factory()->create(['hotel_id' => Hotel::factory()->create()->id]);
        $own = LexiconItem::factory()->create(['hotel_id' => $this->hotel->id]);
        $learner = $this->learner();

        $this->actingAs($learner)
            ->postJson(route('learn.phrasebook.store'), ['lexicon_item_id' => $foreign->id])
            ->assertJsonValidationErrors('lexicon_item_id');

        $this->actingAs($learner)
            ->postJson(route('learn.phrasebook.store'), ['lexicon_item_id' => $own->id])
            ->assertCreated();

        $this->assertDatabaseMissing('phrasebook_items', ['lexicon_item_id' => $foreign->id]);
    }

    public function test_a_source_lesson_out_of_reach_is_refused()
    {
        $foreignLesson = $this->publishedLesson(hotel: Hotel::factory()->create());

        $this->actingAs($this->learner())
            ->postJson(route('learn.phrasebook.store'), ['text' => 'Welcome back!', 'source_lesson_id' => $foreignLesson->id])
            ->assertJsonValidationErrors('source_lesson_id');

        $this->assertDatabaseCount('phrasebook_items', 0);
    }
}

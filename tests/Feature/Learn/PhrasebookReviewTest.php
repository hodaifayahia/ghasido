<?php

namespace Tests\Feature\Learn;

use App\Models\LexiconItem;
use App\Models\PhrasebookItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Spaced review of My Phrasebook (PHRASE-01..05, CTRL-02; spec 0005 §3.4).
 */
class PhrasebookReviewTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    public function test_the_deck_holds_due_cards_with_missed_ones_first()
    {
        $learner = $this->learner();
        $new = PhrasebookItem::factory()->create(['user_id' => $learner->id]);
        $missed = PhrasebookItem::factory()->create(['user_id' => $learner->id, 'lapse_count' => 2, 'due_at' => now()->subHour()]);
        PhrasebookItem::factory()->create(['user_id' => $learner->id, 'review_box' => 3, 'due_at' => now()->addDays(5)]);

        $this->actingAs($learner)
            ->get(route('learn.phrasebook.review'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('employee/PhrasebookReview')
                ->has('cards', 2)
                ->where('cards.0.id', $missed->id)
                ->where('cards.0.reviewCount', 0)
                ->where('cards.1.id', $new->id)
                ->where('totalSaved', 3));

        $this->actingAs($learner)
            ->get(route('learn.phrasebook'))
            ->assertInertia(fn (Assert $page) => $page->where('review.due', 2)->etc());
    }

    public function test_knowing_a_card_moves_it_up_and_out()
    {
        $this->travelTo(now()->setTime(10, 0));
        $learner = $this->learner();
        $item = PhrasebookItem::factory()->create(['user_id' => $learner->id]);

        $this->actingAs($learner)
            ->postJson(route('learn.phrasebook.review.store', $item), ['knew' => true, 'version' => 0])
            ->assertOk()
            ->assertJson(['recorded' => true, 'version' => 1, 'box' => 1, 'needsPractice' => false]);

        $item->refresh();
        $this->assertSame(1, $item->review_box);
        $this->assertSame(1, $item->review_count);
        $this->assertTrue($item->due_at?->isSameDay(now()->addDay()));
        $this->assertNotNull($learner->fresh()?->last_activity_at);
    }

    public function test_missing_a_card_sends_it_back_to_today()
    {
        $learner = $this->learner();
        $item = PhrasebookItem::factory()->create(['user_id' => $learner->id, 'review_box' => 3, 'review_count' => 3]);

        $this->actingAs($learner)
            ->postJson(route('learn.phrasebook.review.store', $item), ['knew' => false, 'version' => 3])
            ->assertOk()
            ->assertJson(['box' => 0, 'needsPractice' => true]);

        $item->refresh();
        $this->assertSame(1, $item->lapse_count);
        $this->assertTrue(PhrasebookItem::query()->whereKey($item->id)->dueForReview()->exists());
    }

    public function test_a_repeated_answer_moves_the_card_once()
    {
        // A retried or doubled request (PROG-04; spec 0005 §5.5): the card
        // moves one box, not two, and the second reply says it was not
        // recorded.
        $learner = $this->learner();
        $item = PhrasebookItem::factory()->create(['user_id' => $learner->id]);

        $this->actingAs($learner)
            ->postJson(route('learn.phrasebook.review.store', $item), ['knew' => true, 'version' => 0])
            ->assertOk()
            ->assertJson(['recorded' => true, 'version' => 1, 'box' => 1]);

        $this->actingAs($learner)
            ->postJson(route('learn.phrasebook.review.store', $item), ['knew' => true, 'version' => 0])
            ->assertOk()
            ->assertJson(['recorded' => false, 'version' => 1, 'box' => 1]);

        $item->refresh();
        $this->assertSame(1, $item->review_box);
        $this->assertSame(1, $item->review_count);
    }

    public function test_an_answer_without_its_version_is_rejected()
    {
        $learner = $this->learner();
        $item = PhrasebookItem::factory()->create(['user_id' => $learner->id]);

        $this->actingAs($learner)
            ->postJson(route('learn.phrasebook.review.store', $item), ['knew' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('version');

        $this->assertSame(0, $item->fresh()?->review_count);
    }

    public function test_someone_elses_card_is_refused()
    {
        $learner = $this->learner();
        $theirs = PhrasebookItem::factory()->create(['user_id' => $this->learner(['username' => 'amine'])->id]);

        $this->actingAs($learner)
            ->postJson(route('learn.phrasebook.review.store', $theirs), ['knew' => true, 'version' => 0])
            ->assertForbidden();

        $this->assertSame(0, $theirs->fresh()?->review_count);
    }

    public function test_the_meaning_is_only_sent_when_the_item_allows_it()
    {
        // Show Meaning stays a per-item switch (CTRL-03); the review deck
        // honours it exactly like the list.
        $learner = $this->learner();
        $hidden = LexiconItem::factory()->create(['show_meaning_enabled' => false]);
        PhrasebookItem::factory()->create(['user_id' => $learner->id, 'lexicon_item_id' => $hidden->id]);

        $this->actingAs($learner)
            ->get(route('learn.phrasebook.review'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('cards.0.showMeaning', false)
                ->where('cards.0.meaning', null)
                ->etc());
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Enums\GenerationStatus;
use App\Jobs\TranslateContent;
use App\Jobs\TranslateText;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\TextTranslation;
use App\Models\User;
use App\Services\Meaning\MeaningTexts;
use App\Services\Meaning\MeaningTranslations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Show Meaning translations are made when content is written, by one AI
 * draft or by the admin's hand, never by a learner's tap (user request
 * 2026-09-26).
 */
class TranslationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_lesson_queues_one_draft_per_new_text_only()
    {
        config(['guesvia.meaning.auto_translate' => true]);
        Queue::fake();

        $lesson = Lesson::factory()->create([
            'title' => 'Welcoming a guest',
            'introduction' => 'Greet every guest with a smile.',
            'objectives' => ['Say hello politely'],
        ]);
        Block::factory()->create(['lesson_id' => $lesson->id, 'settings' => ['body' => 'Offer to carry the luggage.', 'layout' => 'image-left']]);

        Queue::assertPushed(TranslateContent::class);

        // Run the pass: the texts without a meaning get a draft each.
        (new TranslateContent('lesson', $lesson->id))->handle(app(MeaningTranslations::class));

        Queue::assertPushed(TranslateText::class, 4);
        $this->assertSame(4, TextTranslation::query()->where('source', 'ai')->count());
        $this->assertDatabaseMissing('text_translations', ['source_text' => 'image-left']);

        // A second pass sends nothing: every text already has its draft.
        (new TranslateContent('lesson', $lesson->id))->handle(app(MeaningTranslations::class));
        Queue::assertPushed(TranslateText::class, 4);
    }

    public function test_a_meaning_written_by_hand_is_never_replaced_by_the_ai()
    {
        Queue::fake();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->put(route('translations.save'), ['text' => 'Welcoming a guest', 'arabic' => 'استقبال الضيف'])
            ->assertSessionHasNoErrors();

        $translation = TextTranslation::query()->sole();
        $this->assertSame('manual', $translation->source);
        $this->assertSame(GenerationStatus::Done, $translation->status);

        $this->assertSame(0, app(MeaningTranslations::class)->queue(['Welcoming a guest'], $admin));
        Queue::assertNotPushed(TranslateText::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'meaning.updated']);
    }

    public function test_the_admin_generates_the_missing_meanings_of_one_lesson()
    {
        $this->withoutVite();
        Queue::fake();
        $admin = User::factory()->superAdmin()->create();
        $lesson = Lesson::factory()->create(['title' => 'Handling a complaint', 'introduction' => null, 'objectives' => []]);

        $this->actingAs($admin)
            ->get(route('translations', ['content' => 'lesson:'.$lesson->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/Translations')
                ->where('scope.title', 'Handling a complaint')
                ->where('rows.0.text', 'Handling a complaint')
                ->where('rows.0.state', 'missing'));

        $this->actingAs($admin)
            ->post(route('translations.generate'), ['content' => 'lesson:'.$lesson->id])
            ->assertRedirect();

        Queue::assertPushed(TranslateText::class, count(MeaningTexts::forLesson($lesson)));
    }

    public function test_a_learner_or_a_manager_cannot_manage_translations()
    {
        foreach ([User::factory()->employee()->create(), User::factory()->manager()->create()] as $user) {
            $this->actingAs($user)->get(route('translations'))->assertForbidden();
            $this->actingAs($user)->put(route('translations.save'), ['text' => 'Hello', 'arabic' => 'مرحبا'])->assertForbidden();
            $this->actingAs($user)->post(route('translations.generate'))->assertForbidden();
        }

        $this->assertDatabaseCount('text_translations', 0);
    }

    public function test_a_builder_field_reads_the_meaning_of_its_text_without_creating_one()
    {
        Queue::fake();
        $admin = User::factory()->superAdmin()->create();
        TextTranslation::query()->create([
            'hash' => TextTranslation::hashOf('Welcoming a guest'),
            'source_text' => 'Welcoming a guest',
            'arabic' => 'استقبال الضيف',
            'status' => GenerationStatus::Done,
            'source' => 'ai',
        ]);

        // Same key whatever the spacing and case (TextTranslation::hashOf).
        $this->actingAs($admin)
            ->postJson(route('translations.lookup'), ['texts' => ['  welcoming   a GUEST ', 'Say hello politely']])
            ->assertOk()
            ->assertJsonPath('items.0.arabic', 'استقبال الضيف')
            ->assertJsonPath('items.0.state', 'ai')
            ->assertJsonPath('items.1.arabic', null)
            ->assertJsonPath('items.1.state', 'missing');

        // Looking up never notes a text as requested nor asks the AI.
        $this->assertDatabaseCount('text_translations', 1);
        Queue::assertNothingPushed();
    }

    public function test_a_builder_field_saves_its_meaning_as_json_and_it_reaches_the_learner()
    {
        Queue::fake();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->putJson(route('translations.save'), ['text' => 'Say hello politely', 'arabic' => 'قل مرحبًا بأدب'])
            ->assertOk()
            ->assertJsonPath('text', 'Say hello politely')
            ->assertJsonPath('arabic', 'قل مرحبًا بأدب')
            ->assertJsonPath('state', 'manual');

        $this->actingAs(User::factory()->employee()->create())
            ->postJson(route('meaning'), ['text' => 'Say hello politely'])
            ->assertOk()
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('arabic', 'قل مرحبًا بأدب');
    }

    public function test_a_learner_or_a_manager_cannot_read_or_write_builder_meanings()
    {
        foreach ([User::factory()->employee()->create(), User::factory()->manager()->create()] as $user) {
            $this->actingAs($user)->postJson(route('translations.lookup'), ['texts' => ['Hello']])->assertForbidden();
            $this->actingAs($user)->putJson(route('translations.save'), ['text' => 'Hello', 'arabic' => 'مرحبا'])->assertForbidden();
        }

        $this->assertDatabaseCount('text_translations', 0);
    }

    public function test_the_ai_job_skips_a_meaning_the_admin_wrote_meanwhile()
    {
        $translation = TextTranslation::query()->create([
            'hash' => TextTranslation::hashOf('Hello'),
            'source_text' => 'Hello',
            'arabic' => 'أهلا',
            'status' => GenerationStatus::Pending,
            'source' => 'manual',
        ]);

        dispatch_sync(new TranslateText($translation->id));

        $this->assertSame('أهلا', $translation->refresh()->arabic);
    }
}

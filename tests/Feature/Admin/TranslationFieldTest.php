<?php

namespace Tests\Feature\Admin;

use App\Enums\AiFeature;
use App\Enums\GenerationStatus;
use App\Jobs\TranslateText;
use App\Models\AiUsage;
use App\Models\TextTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * The "Translate meaning to Arabic" button above every English field of the
 * test and lesson builders (client request 2026-09-29): look up, draft with
 * AI, save by hand.
 */
class TranslationFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_editor_looks_up_the_meaning_of_several_fields()
    {
        $admin = User::factory()->superAdmin()->create();
        TextTranslation::query()->create([
            'hash' => TextTranslation::hashOf('Welcome the guest'),
            'source_text' => 'Welcome the guest',
            'translation' => 'رحّب بالضيف',
            'status' => GenerationStatus::Done,
            'source' => 'manual',
        ]);

        $this->actingAs($admin)
            ->postJson(route('translations.lookup'), ['texts' => ['  welcome   the guest ', 'Offer water', '']])
            ->assertOk()
            ->assertJsonPath('items.0.state', 'manual')
            ->assertJsonPath('items.0.arabic', 'رحّب بالضيف')
            ->assertJsonPath('items.1.state', 'missing')
            ->assertJsonPath('items.1.arabic', null)
            ->assertJsonPath('items.2.state', 'missing');
    }

    public function test_a_draft_runs_after_the_response_and_is_metered()
    {
        config(['services.ai.provider' => 'fake']);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->postJson(route('translations.draft'), ['text' => 'Check the booking'])
            ->assertStatus(202)
            ->assertJsonPath('item.state', 'drafting');

        // The HTTP kernel's terminate ran the job right after the response.
        $translation = TextTranslation::query()->sole();
        $this->assertSame(GenerationStatus::Done, $translation->status);
        $this->assertSame('ai', $translation->source);
        $this->assertStringContainsString('Check the booking', (string) $translation->translation);
        $this->assertSame(1, AiUsage::query()->where('feature', AiFeature::Translation->value)->where('user_id', $admin->id)->count());

        $this->actingAs($admin)
            ->postJson(route('translations.lookup'), ['texts' => ['Check the booking']])
            ->assertJsonPath('items.0.state', 'ai');
    }

    public function test_the_draft_is_dispatched_after_the_response()
    {
        Bus::fake();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->postJson(route('translations.draft'), ['text' => 'Offer help'])
            ->assertStatus(202);

        Bus::assertDispatchedAfterResponse(TranslateText::class);
    }

    public function test_saving_by_hand_is_never_overwritten_by_a_later_ai_draft()
    {
        Bus::fake();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->postJson(route('translations.write'), ['text' => 'Room service', 'arabic' => 'خدمة الغرف'])
            ->assertOk()
            ->assertJsonPath('item.state', 'manual')
            ->assertJsonPath('item.arabic', 'خدمة الغرف');

        $this->actingAs($admin)
            ->postJson(route('translations.draft'), ['text' => 'Room service'])
            ->assertOk()
            ->assertJsonPath('item.state', 'manual');

        Bus::assertNotDispatched(TranslateText::class);
        $this->assertSame('خدمة الغرف', TextTranslation::query()->sole()->translation);
        $this->assertDatabaseHas('audit_logs', ['action' => 'meaning.updated']);
    }

    public function test_an_empty_text_cannot_be_drafted()
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->postJson(route('translations.draft'), ['text' => '  '])->assertStatus(422);
        $this->actingAs($admin)->postJson(route('translations.draft'), ['text' => '123 !'])->assertStatus(422);

        $this->assertDatabaseCount('text_translations', 0);
    }

    public function test_a_learner_or_a_manager_cannot_use_the_field_button()
    {
        foreach ([User::factory()->employee()->create(), User::factory()->manager()->create()] as $user) {
            $this->actingAs($user)->postJson(route('translations.lookup'), ['texts' => ['Hello']])->assertForbidden();
            $this->actingAs($user)->postJson(route('translations.draft'), ['text' => 'Hello'])->assertForbidden();
            $this->actingAs($user)->postJson(route('translations.write'), ['text' => 'Hello', 'arabic' => 'مرحبا'])->assertForbidden();
        }

        $this->assertDatabaseCount('text_translations', 0);
    }
}

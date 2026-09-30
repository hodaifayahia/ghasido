<?php

namespace Tests\Feature\Learn;

use App\Enums\BlockType;
use App\Enums\GenerationStatus;
use App\Models\TextTranslation;
use App\Models\User;
use App\Services\Meaning\HelperLanguages;
use App\Services\Meaning\HelperMeaningSwap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Helper languages (client request 2026-09-30): Show Meaning in Arabic,
 * French or any language the Super Admin adds, without a developer. The
 * learner chooses theirs and it is kept on the account.
 */
class HelperLanguageTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();
    }

    private function translated(string $text, string $locale, string $meaning): void
    {
        TextTranslation::query()->create([
            'hash' => TextTranslation::hashOf($text),
            'locale' => $locale,
            'source_text' => $text,
            'translation' => $meaning,
            'status' => GenerationStatus::Done,
            'source' => 'manual',
        ]);
    }

    public function test_arabic_and_french_are_on_and_arabic_is_the_default()
    {
        $languages = app(HelperLanguages::class);

        $this->assertSame(['ar', 'fr'], $languages->activeCodes());
        $this->assertSame('ar', $languages->defaultCode());
        $this->assertSame('rtl', $languages->direction('ar'));
        $this->assertSame('ltr', $languages->direction('fr'));
    }

    public function test_a_learner_reads_meanings_in_the_language_they_chose()
    {
        $learner = $this->learner();
        $this->translated('Check-in basics', 'ar', 'أساسيات تسجيل الوصول');
        $this->translated('Check-in basics', 'fr', 'Les bases de l’enregistrement');

        $this->actingAs($learner)->postJson(route('meaning'), ['text' => 'Check-in basics'])
            ->assertJsonPath('text', 'أساسيات تسجيل الوصول')
            ->assertJsonPath('dir', 'rtl');

        $this->actingAs($learner)->put(route('learn.helper-language.update'), ['language' => 'fr'])->assertRedirect();
        $this->assertSame('fr', $learner->fresh()?->meaning_locale);

        $this->actingAs($learner->fresh() ?? $learner)->postJson(route('meaning'), ['text' => 'Check-in basics'])
            ->assertJsonPath('text', 'Les bases de l’enregistrement')
            ->assertJsonPath('locale', 'fr')
            ->assertJsonPath('dir', 'ltr');
    }

    public function test_a_language_that_is_off_cannot_be_chosen()
    {
        $learner = $this->learner();

        $this->actingAs($learner)->put(route('learn.helper-language.update'), ['language' => 'ms'])
            ->assertSessionHasErrors('language');
    }

    public function test_the_super_admin_adds_a_language_and_learners_can_pick_it()
    {
        $admin = User::factory()->superAdmin()->create();
        $learner = $this->learner();

        $this->actingAs($learner)->put(route('learning-settings.languages'), [])->assertForbidden();

        $payload = [
            'languages' => [
                ['code' => 'ar', 'name' => 'Arabic', 'native' => 'العربية', 'dir' => 'rtl', 'active' => true],
                ['code' => 'fr', 'name' => 'French', 'native' => 'Français', 'dir' => 'ltr', 'active' => true],
                ['code' => 'ms', 'name' => 'Malay', 'native' => 'Bahasa Melayu', 'dir' => 'ltr', 'active' => true],
            ],
            'default' => 'ar',
        ];

        $this->actingAs($admin)->put(route('learning-settings.languages'), $payload)->assertSessionHasNoErrors();

        $this->app->forgetScopedInstances();
        $this->assertSame(['ar', 'fr', 'ms'], app(HelperLanguages::class)->activeCodes());

        $this->actingAs($learner)->put(route('learn.helper-language.update'), ['language' => 'ms'])
            ->assertSessionHasNoErrors();
        $this->assertSame('ms', $learner->fresh()?->meaning_locale);
    }

    public function test_a_language_is_never_removed_only_switched_off()
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->put(route('learning-settings.languages'), [
            'languages' => [
                ['code' => 'ar', 'name' => 'Arabic', 'native' => 'العربية', 'dir' => 'rtl', 'active' => true],
            ],
            'default' => 'ar',
        ])->assertSessionHasErrors('languages');

        $this->actingAs($admin)->put(route('learning-settings.languages'), [
            'languages' => [
                ['code' => 'ar', 'name' => 'Arabic', 'native' => 'العربية', 'dir' => 'rtl', 'active' => false],
                ['code' => 'fr', 'name' => 'French', 'native' => 'Français', 'dir' => 'ltr', 'active' => true],
            ],
            'default' => 'ar',
        ])->assertSessionHasErrors('default');
    }

    public function test_a_french_reader_never_gets_the_inline_arabic_of_a_lesson()
    {
        $learner = $this->learner(['meaning_locale' => 'fr']);
        $this->translated('How can I help you?', 'fr', 'Comment puis-je vous aider ?');

        $payload = [
            'settings' => ['items' => [
                ['text' => 'How can I help you?', 'arabic' => 'كيف يمكنني مساعدتك؟'],
                ['text' => 'One moment, please.', 'arabic' => 'لحظة من فضلك'],
            ]],
            'lexicon' => [[
                'text' => 'luggage',
                'example' => 'May I help you with your luggage?',
                'meaning' => ['arabic' => 'أمتعة', 'explanation' => 'Bags.', 'exampleArabic' => 'هل أساعدك؟'],
            ]],
        ];

        $swapped = app(HelperMeaningSwap::class)->apply($payload, $learner);

        $this->assertSame('Comment puis-je vous aider ?', $swapped['settings']['items'][0]['arabic']);
        $this->assertNull($swapped['settings']['items'][1]['arabic']);
        $this->assertNull($swapped['lexicon'][0]['meaning']['arabic']);
        $this->assertNull($swapped['lexicon'][0]['meaning']['exampleArabic']);
        $this->assertSame('Bags.', $swapped['lexicon'][0]['meaning']['explanation']);

        // An Arabic reader gets the lesson as it was written.
        $arabic = $this->learner(['username' => 'amine']);
        $this->assertSame($payload, app(HelperMeaningSwap::class)->apply($payload, $arabic));
    }

    public function test_the_first_login_screen_offers_the_helper_languages()
    {
        $learner = $this->learner(['first_login_completed_at' => null, 'research_notice_acknowledged_at' => null]);

        $this->actingAs($learner)->get(route('learn.first-login.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('helperLanguage', 'ar')
                ->has('helperLanguages', 2));

        $this->actingAs($learner)->post(route('learn.first-login.update'), [
            'research_notice_acknowledged' => '1',
            'level' => 'beginner',
            'helper_language' => 'fr',
        ])->assertRedirect(route('learn.home'));

        $this->assertSame('fr', $learner->fresh()?->meaning_locale);
    }

    public function test_a_lesson_step_uses_the_learners_helper_language()
    {
        $learner = $this->learner(['meaning_locale' => 'fr']);
        $lesson = $this->publishedLesson([BlockType::Situation]);
        $block = $lesson->visibleBlocks()->firstOrFail();
        $block->update(['settings' => ['items' => [['text' => 'Welcome!', 'arabic' => 'أهلا']]]]);

        $this->actingAs($learner)
            ->get(route('learn.lessons.step', ['lesson' => $lesson, 'block' => $block]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('helperLanguage.code', 'fr')
                ->where('block.settings.items.0.arabic', null));
    }
}

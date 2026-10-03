<?php

namespace Tests\Feature\I18n;

use App\Models\Hotel;
use App\Models\InterfaceLanguage;
use App\Models\User;
use App\Services\I18n\InterfaceLanguageGenerator;
use App\Services\I18n\InterfaceLanguages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Client request 2026-10-03: the Super Admin adds any interface language in
 * Settings, translates it with AI or by hand, and switches it on.
 */
class InterfaceLanguagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_super_admin_adds_translates_and_enables_a_language()
    {
        $owner = User::factory()->superAdmin()->create();

        $this->actingAs($owner)
            ->post(route('interface-languages.store'), ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr'])
            ->assertSessionHasNoErrors();

        $language = InterfaceLanguage::query()->where('code', 'fr')->sole();
        $this->assertFalse($language->enabled);
        $this->assertNotContains('fr', InterfaceLanguages::codes());

        // The fake provider answers every batch; the page calls until done.
        $total = count(InterfaceLanguages::sourceStrings());
        for ($round = 0; $round < 20 && $language->refresh()->status !== 'ready'; $round++) {
            $this->actingAs($owner)->post(route('interface-languages.generate', $language))->assertRedirect();
        }

        $this->assertSame('ready', $language->status);
        $this->assertCount($total, $language->translations());
        $this->assertSame('[French] Dashboard', $language->translations()['Dashboard']);

        $this->actingAs($owner)
            ->get(route('interface-languages.index', ['language' => 'fr', 'search' => 'Dashboard']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/InterfaceLanguages')
                ->where('total', $total)
                ->where('languages.0.translated', $total)
                ->where('editor.matching', fn ($count) => $count >= 1));

        // A line corrected by hand.
        $this->actingAs($owner)
            ->patch(route('interface-languages.strings.update', $language), ['key' => 'Dashboard', 'value' => 'Tableau de bord'])
            ->assertSessionHasNoErrors();

        $this->actingAs($owner)->patch(route('interface-languages.update', $language), ['enabled' => true]);
        $this->assertContains('fr', InterfaceLanguages::codes());

        // Anyone can now pick it; the server speaks it and the menu lists it.
        $this->actingAs($owner)->put(route('locale.update'), ['locale' => 'fr'])->assertSessionHasNoErrors();
        $this->assertSame('fr', $owner->refresh()->locale);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale.current', 'fr')
                ->where('locale.direction', 'ltr')
                ->where('locale.available.2.code', 'fr'));
        $this->assertSame('Tableau de bord', __('Dashboard', [], 'fr'));

        $this->get(route('locale.messages', 'fr'))
            ->assertOk()
            ->assertJsonPath('Dashboard', 'Tableau de bord');
    }

    public function test_a_translation_that_drops_a_placeholder_is_not_kept()
    {
        $this->assertTrue(InterfaceLanguageGenerator::keepsPlaceholders('Hello :name', 'Bonjour :name'));
        $this->assertFalse(InterfaceLanguageGenerator::keepsPlaceholders('Hello :name', 'Bonjour'));
    }

    public function test_built_in_languages_cannot_be_added_and_a_json_file_imports()
    {
        $owner = User::factory()->superAdmin()->create();

        $this->actingAs($owner)
            ->post(route('interface-languages.store'), ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'direction' => 'rtl'])
            ->assertSessionHasErrors('code');

        $language = InterfaceLanguage::query()->create(['code' => 'tr', 'name' => 'Turkish', 'native_name' => 'Türkçe', 'direction' => 'ltr', 'messages' => []]);
        $file = UploadedFile::fake()->createWithContent('tr.json', (string) json_encode(['Dashboard' => 'Panel', 'Not a real key' => 'x']));

        $this->actingAs($owner)
            ->post(route('interface-languages.import', $language), ['file' => $file])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Dashboard' => 'Panel'], $language->refresh()->translations());

        $this->actingAs($owner)->get(route('interface-languages.export', $language))->assertOk();
    }

    public function test_a_disabled_language_cannot_be_chosen_and_only_the_super_admin_manages_languages()
    {
        InterfaceLanguage::query()->create(['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'direction' => 'ltr', 'enabled' => false]);
        $hotel = Hotel::factory()->active()->create();
        $manager = User::factory()->manager()->create(['hotel_id' => $hotel->id]);

        $this->actingAs($manager)->put(route('locale.update'), ['locale' => 'de'])->assertSessionHasErrors('locale');
        $this->get(route('locale.messages', 'de'))->assertNotFound();

        $this->actingAs($manager)->get(route('interface-languages.index'))->assertForbidden();
        $this->actingAs($manager)
            ->post(route('interface-languages.store'), ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'direction' => 'ltr'])
            ->assertForbidden();
    }
}

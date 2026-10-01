<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Settings → Helper language (client request 2026-10-01): every role picks
 * the Show Meaning language, with flags; until they do, the app asks once
 * on sign-in (`helperLanguage.chosen`).
 */
class HelperLanguageSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return [
            'super admin' => ['superAdmin'],
            'admin' => ['admin'],
            'manager' => ['manager'],
        ];
    }

    #[DataProvider('roles')]
    public function test_every_role_chooses_and_changes_its_helper_language(string $role)
    {
        $user = User::factory()->{$role}()->create(['meaning_locale' => null]);

        $this->actingAs($user)
            ->get(route('helper-language.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/HelperLanguage')
                ->where('helperLanguage.chosen', false)
                ->where('helperLanguage.options.0.value', 'ar')
                ->where('helperLanguage.options.0.flag', 'dz')
                ->where('helperLanguage.options.1.flag', 'fr'));

        $this->actingAs($user)
            ->put(route('helper-language.update'), ['language' => 'fr'])
            ->assertRedirect();

        $this->assertSame('fr', $user->refresh()->meaning_locale);

        $this->actingAs($user)
            ->get(route('helper-language.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('helperLanguage.chosen', true)
                ->where('helperLanguage.code', 'fr'));

        $this->actingAs($user)->put(route('helper-language.update'), ['language' => 'ar']);
        $this->assertSame('ar', $user->refresh()->meaning_locale);
    }

    public function test_an_employee_chooses_it_in_settings_too()
    {
        $user = User::factory()->employee()->create(['meaning_locale' => 'ar']);

        $this->actingAs($user)
            ->put(route('helper-language.update'), ['language' => 'fr'])
            ->assertRedirect();

        $this->assertSame('fr', $user->refresh()->meaning_locale);
    }

    public function test_only_an_offered_language_can_be_chosen()
    {
        $user = User::factory()->manager()->create();

        $this->actingAs($user)
            ->put(route('helper-language.update'), ['language' => 'xx'])
            ->assertSessionHasErrors('language');
    }

    public function test_guests_are_sent_to_sign_in()
    {
        $this->get(route('helper-language.edit'))->assertRedirect(route('login'));
    }
}

<?php

namespace Tests\Feature\I18n;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The English / Arabic interface switch (I18N-02). */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_pages_default_to_english_left_to_right(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr"', false)
            ->assertInertia(fn ($page) => $page
                ->where('locale.current', 'en')
                ->where('locale.direction', 'ltr'));
    }

    public function test_a_guest_can_switch_to_arabic_and_the_page_turns_right_to_left(): void
    {
        $this->from(route('home'))
            ->put(route('locale.update'), ['locale' => 'ar'])
            ->assertRedirect(route('home'))
            ->assertCookie('locale', 'ar', false);

        $this->withUnencryptedCookie('locale', 'ar')
            ->get(route('home'))
            ->assertSee('<html lang="ar" dir="rtl"', false)
            ->assertInertia(fn ($page) => $page
                ->where('locale.current', 'ar')
                ->where('locale.direction', 'rtl'));
    }

    public function test_a_signed_in_user_keeps_their_language_on_every_device(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->put(route('locale.update'), ['locale' => 'ar'])
            ->assertRedirect(route('dashboard'));

        $this->assertSame('ar', $user->fresh()?->locale);

        // A new browser without the cookie still gets Arabic.
        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('locale.current', 'ar'));
    }

    public function test_server_messages_follow_the_chosen_language(): void
    {
        $this->withUnencryptedCookie('locale', 'ar')->get(route('home'));

        $this->assertSame('ar', app()->getLocale());
    }

    public function test_an_unknown_language_is_rejected_and_nothing_changes(): void
    {
        $this->from(route('home'))
            ->put(route('locale.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale')
            ->assertCookieMissing('locale');

        $this->withUnencryptedCookie('locale', 'fr')
            ->get(route('home'))
            ->assertInertia(fn ($page) => $page->where('locale.current', 'en'));
    }
}

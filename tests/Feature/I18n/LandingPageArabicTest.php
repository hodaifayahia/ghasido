<?php

namespace Tests\Feature\I18n;

use App\Models\User;
use App\Services\Landing\LandingPageArabicDefaults;
use App\Services\Landing\LandingPageContentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** The Arabic copy of the public site (I18N-02, user request 2026-09-26). */
class LandingPageArabicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_arabic_defaults_cover_every_english_section_except_the_shared_contact_details(): void
    {
        $english = app(LandingPageContentStore::class)->defaults();
        $arabic = LandingPageArabicDefaults::all();

        unset($english['support']);

        $this->assertSame($this->shape($english), $this->shape($arabic));
    }

    public function test_an_arabic_visitor_sees_the_arabic_copy_and_an_english_visitor_the_english_one(): void
    {
        // English first: a cookie set on the test carries to later requests.
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.navigation.login', 'Log in'));

        $this->withUnencryptedCookie('locale', 'ar')
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.hero.title', LandingPageArabicDefaults::all()['hero']['title'])
                ->where('content.navigation.login', 'تسجيل الدخول'));

        $this->withUnencryptedCookie('locale', 'ar')
            ->get(route('contact'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.contact.eyebrow', 'اتصل بنا'));
    }

    public function test_the_super_admin_saves_the_arabic_copy_without_touching_the_english_one(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $store = app(LandingPageContentStore::class);

        $english = $store->current('en');
        $english['support']['phone'] = '+213 21 00 00 00';
        $this->actingAs($admin)
            ->from(route('landing-page.edit'))
            ->patch(route('landing-page.update'), ['content' => $english])
            ->assertSessionHasNoErrors();

        $arabic = $store->current('ar');
        $arabic['hero']['title'] = 'عنوان عربي جديد';
        // A different number sent with the Arabic copy is ignored: the
        // contact details belong to the English copy.
        $arabic['support']['phone'] = '+213 99 99 99 99';
        $arabic['roles']['items'] = [$arabic['roles']['items'][0]];

        $this->actingAs($admin)
            ->from(route('landing-page.edit'))
            ->patch(route('landing-page.update'), ['locale' => 'ar', 'content' => $arabic])
            ->assertSessionHasNoErrors();

        $this->assertSame('عنوان عربي جديد', $store->current('ar')['hero']['title']);
        $this->assertCount(1, $store->current('ar')['roles']['items']);
        $this->assertSame('+213 21 00 00 00', $store->current('ar')['support']['phone']);
        $this->assertSame($english['hero']['title'], $store->current('en')['hero']['title']);
        $this->assertSame('+213 21 00 00 00', $store->current('en')['support']['phone']);

        $this->actingAs($admin)
            ->get(route('landing-page.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('contentAr.hero.title', 'عنوان عربي جديد')
                ->where('content.hero.title', $english['hero']['title']));
    }

    public function test_an_unknown_copy_language_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->from(route('landing-page.edit'))
            ->patch(route('landing-page.update'), [
                'locale' => 'fr',
                'content' => app(LandingPageContentStore::class)->current('en'),
            ])
            ->assertSessionHasErrors('locale');
    }

    /**
     * The keys of a nested array, with list items reduced to their shape.
     *
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function shape(array $value): array
    {
        $shape = [];

        foreach ($value as $key => $item) {
            $shape[$key] = is_array($item) ? $this->shape($item) : gettype($item);
        }

        ksort($shape);

        return $shape;
    }
}

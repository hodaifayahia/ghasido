<?php

namespace Tests\Feature\Settings;

use App\Models\Hotel;
use App\Models\LandingPageContent;
use App\Models\User;
use App\Services\Landing\LandingPageContentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** WhatsApp support settings and public landing page data (ADM-02, SEC-01). */
class LandingPageSupportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_super_admin_can_save_a_whatsapp_number_and_it_is_given_to_the_public_landing_page(): void
    {
        $owner = User::factory()->superAdmin()->create();
        $content = app(LandingPageContentStore::class)->current();
        $content['support']['whatsapp_number'] = '+213 555 12 34 56';

        $this->actingAs($owner)
            ->from(route('landing-page.edit'))
            ->patch(route('landing-page.update'), ['content' => $content])
            ->assertRedirect(route('landing-page.edit'))
            ->assertSessionHasNoErrors();

        $this->assertSame('+213 555 12 34 56', LandingPageContent::query()->findOrFail(1)->content['support']['whatsapp_number']);

        $this->actingAs($owner)
            ->get(route('landing-page.edit'))
            ->assertInertia(fn ($page) => $page
                ->component('settings/LandingPage')
                ->where('content.support.whatsapp_number', '+213 555 12 34 56'));

        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('Welcome')
                ->where('content.support.whatsapp_number', '+213 555 12 34 56'));
    }

    public function test_invalid_whatsapp_numbers_are_rejected_and_blank_contact_is_supported(): void
    {
        $owner = User::factory()->superAdmin()->create();
        $content = app(LandingPageContentStore::class)->current();
        $content['support']['whatsapp_number'] = 'call me on whatsapp';

        $this->actingAs($owner)
            ->from(route('landing-page.edit'))
            ->patch(route('landing-page.update'), ['content' => $content])
            ->assertSessionHasErrors('content.support.whatsapp_number');

        $content['support']['whatsapp_number'] = '';
        $this->actingAs($owner)
            ->from(route('landing-page.edit'))
            ->patch(route('landing-page.update'), ['content' => $content])
            ->assertRedirect(route('landing-page.edit'))
            ->assertSessionHasNoErrors();

        $this->get('/')
            ->assertInertia(fn ($page) => $page->component('Welcome')->where('content.support.whatsapp_number', ''));
    }

    public function test_managers_cannot_change_the_platform_support_number(): void
    {
        $manager = User::factory()->manager()->create(['hotel_id' => Hotel::factory()->create()->id]);

        $this->actingAs($manager)
            ->get(route('landing-page.edit'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->patch(route('landing-page.update'), ['content' => app(LandingPageContentStore::class)->current()])
            ->assertForbidden();
    }
}

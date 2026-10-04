<?php

namespace Tests\Feature\Seo;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client report 2026-10-04: the site should come up when people search for
 * it. Search engines read the server's HTML first.
 */
class SearchEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_landing_page_carries_its_title_description_and_readable_text()
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>GHASIDO – Hotel English your team can use on their next shift.</title>', false)
            ->assertSee('"@context":"https://schema.org"', false)
            ->assertDontSee('__contextArgs', false)
            ->assertSee('<meta name="description" content="GHASIDO brings lessons', false)
            ->assertSee('<meta name="robots" content="index, follow', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('"@type":"SoftwareApplication"', false)
            ->assertSee('id="seo-fallback"', false)
            ->assertSee('<h1', false)
            ->assertSee('Training made for the hotel floor.');
    }

    public function test_private_screens_are_kept_out_of_search_results()
    {
        $this->get(route('login'))->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('dashboard'))
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_the_sitemap_lists_the_public_pages_and_robots_points_to_it()
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.url('/').'</loc>', false)
            ->assertSee('<loc>'.url('/contact').'</loc>', false);

        $this->assertStringContainsString('Sitemap: https://ghasido.com/sitemap.xml', (string) file_get_contents(public_path('robots.txt')));
    }
}

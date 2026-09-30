<?php

namespace Tests\Feature\Seo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Google shows the site's favicon and logo next to the search result
 * (user request 2026-09-30).
 */
class SearchResultLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_page_declares_its_icons_and_logo(): void
    {
        $this->withoutVite();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('/favicon-48x48.png', false)
            ->assertSee('/favicon-96x96.png', false)
            ->assertSee('rel="canonical"', false)
            ->assertSee('"@type":"Organization"', false)
            ->assertSee('/brand/ghasido-app-512.png', false);

        $this->assertFileExists(public_path('favicon-48x48.png'));
        $this->assertFileExists(public_path('favicon-96x96.png'));
    }
}

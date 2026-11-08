<?php

namespace Tests\Feature\Security;

use App\Enums\BlockType;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Support\SessionKey;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * Security audit pass 2 (2026-11-08): public form throttling, stored file
 * names and headers, logout history and the inline appearance value.
 */
class PlatformHardeningTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    public function test_hotel_signup_is_rate_limited()
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('hotel-signup.store'), [])->assertStatus(302);
        }

        $this->post(route('hotel-signup.store'), [])->assertStatus(429);
    }

    public function test_a_recording_is_stored_with_the_extension_of_its_detected_type()
    {
        $this->setUpLearnerFixtures();
        Storage::fake(MediaAsset::DISK_LOCAL);
        $learner = $this->learner();
        $block = $this->publishedLesson([BlockType::Practice])->visibleBlocks()->firstOrFail();

        $response = $this->actingAs($learner)->postJson(route('learn.recordings.store'), [
            'audio' => UploadedFile::fake()->create('answer.html', 120, 'audio/wav'),
            'recordable_type' => 'block',
            'recordable_id' => $block->id,
            'duration_ms' => 1000,
        ])->assertCreated();

        $asset = MediaAsset::query()->findOrFail($response->json('id'));

        $this->assertStringEndsWith('.wav', $asset->path);
    }

    public function test_private_media_is_served_with_nosniff_and_a_sandbox_policy()
    {
        $this->setUpLearnerFixtures();
        Storage::fake(MediaAsset::DISK_LOCAL);
        $learner = $this->learner();
        $block = $this->publishedLesson([BlockType::Practice])->visibleBlocks()->firstOrFail();

        $id = $this->actingAs($learner)->postJson(route('learn.recordings.store'), [
            'audio' => UploadedFile::fake()->create('answer.wav', 12, 'audio/wav'),
            'recordable_type' => 'block',
            'recordable_id' => $block->id,
            'duration_ms' => 1000,
        ])->json('id');

        $this->actingAs($learner)->get(route('media.show', $id))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', 'sandbox');
    }

    public function test_logging_out_clears_the_browser_page_history()
    {
        $user = User::factory()->employee()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect();

        $this->assertTrue(session(SessionKey::CLEAR_HISTORY));
    }

    public function test_an_unknown_appearance_cookie_never_reaches_the_inline_script()
    {
        $this->withUnencryptedCookie('appearance', "x';alert(1);//")
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('alert(1)', false)
            ->assertSee("const appearance = 'light';", false);
    }
}

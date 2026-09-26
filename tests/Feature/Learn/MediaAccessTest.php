<?php

namespace Tests\Feature\Learn;

use App\Models\Hotel;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The private media route (MED-02, PRIV-04, SEC-04; spec 0003 B.3).
 *
 * A learner's recording is served to its owner, to the Super Admin, and to
 * nobody outside their hotel. A public asset just points at storage.
 */
class MediaAccessTest extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLearnerFixtures();

        Storage::fake(MediaAsset::DISK_LOCAL);
    }

    public function test_the_owner_can_read_their_own_recording()
    {
        $learner = $this->learner();
        $asset = $this->recordingOf($learner);

        $response = $this->actingAs($learner)->get(route('media.show', $asset));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'audio/webm');
    }

    public function test_another_employee_is_refused_with_a_403()
    {
        $learner = $this->learner();
        $asset = $this->recordingOf($learner);
        $stranger = User::factory()->employee()->firstLoginDone()->create();

        $this->actingAs($stranger)->get(route('media.show', $asset))->assertForbidden();
    }

    public function test_a_manager_of_another_hotel_is_refused()
    {
        $asset = $this->recordingOf($this->learner());
        $foreignManager = User::factory()->manager()->create(['hotel_id' => Hotel::factory()->create()->id]);

        $this->actingAs($foreignManager)->get(route('media.show', $asset))->assertForbidden();
    }

    public function test_the_own_hotels_manager_and_hotel_admin_are_refused_a_recording()
    {
        // Recordings are Super Admin only (ROLE-04, PRIV-04): being in the
        // learner's hotel is not enough.
        $asset = $this->recordingOf($this->learner());

        $ownManager = User::factory()->manager()->create(['hotel_id' => $this->hotel->id]);
        $ownAdmin = User::factory()->admin()->create(['hotel_id' => $this->hotel->id]);

        $this->actingAs($ownManager)->get(route('media.show', $asset))->assertForbidden();
        $this->actingAs($ownAdmin)->get(route('media.show', $asset))->assertForbidden();
    }

    public function test_a_coworker_in_the_same_hotel_is_refused()
    {
        $asset = $this->recordingOf($this->learner());
        $coworker = $this->learner(['username' => 'coworker']);

        $this->actingAs($coworker)->get(route('media.show', $asset))->assertForbidden();
    }

    public function test_the_super_admin_can_read_any_recording()
    {
        $asset = $this->recordingOf($this->learner());

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('media.show', $asset))
            ->assertOk();
    }

    public function test_a_guest_is_sent_to_login()
    {
        $asset = $this->recordingOf($this->learner());

        $this->get(route('media.show', $asset))->assertRedirect(route('login'));
    }

    public function test_a_public_asset_redirects_to_its_storage_url()
    {
        $asset = MediaAsset::factory()->seed('situation-complaint')->create();

        $this->actingAs($this->learner())
            ->get(route('media.show', $asset))
            ->assertRedirect(is_file(public_path('storage/'.$asset->path))
                ? Storage::disk(MediaAsset::DISK_PUBLIC)->url($asset->path)
                : asset($asset->path));
    }

    public function test_a_missing_file_behind_a_private_row_is_a_404_not_a_500()
    {
        $learner = $this->learner();
        $asset = MediaAsset::factory()->recording()->create(['uploaded_by' => $learner->id, 'hotel_id' => $this->hotel->id]);

        $this->actingAs($learner)->get(route('media.show', $asset))->assertNotFound();
    }

    private function recordingOf(User $learner): MediaAsset
    {
        $asset = MediaAsset::factory()->recording()->create([
            'uploaded_by' => $learner->id,
            'hotel_id' => $learner->hotel_id,
        ]);

        Storage::disk(MediaAsset::DISK_LOCAL)->put($asset->path, 'RIFF-not-really-audio');

        return $asset;
    }
}

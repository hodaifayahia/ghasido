<?php

namespace Tests\Feature\Security;

use App\Enums\Permission;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\MediaAsset;
use App\Models\PronunciationAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Security audit pass 2 (2026-11-08): transcripts.view granted to a
 * hotel-bound role opens its own hotel's learner recordings and
 * pronunciation checks only (ROLE-02, ROLE-04, PRIV-04).
 */
class TenancyRecordingsTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $home;

    private Hotel $other;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->home = Hotel::factory()->create();
        $this->other = Hotel::factory()->create();
        $this->reviewer = User::factory()->admin()->forHotel($this->home, Department::factory()->create())->create();
        $this->reviewer->givePermissionTo(Permission::TranscriptsView->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_a_hotel_reviewer_opens_only_their_own_hotels_recordings()
    {
        $own = MediaAsset::factory()->recording()->forHotel($this->home->id)->create();
        $foreign = MediaAsset::factory()->recording()->forHotel($this->other->id)->create();
        $unbound = MediaAsset::factory()->recording()->create();

        $this->assertTrue($this->reviewer->can('view', $own));
        $this->assertFalse($this->reviewer->can('view', $foreign));
        $this->assertFalse($this->reviewer->can('view', $unbound));
    }

    public function test_the_recording_owner_and_the_super_admin_keep_access()
    {
        $learner = User::factory()->employee()->create(['hotel_id' => $this->other->id]);
        $recording = MediaAsset::factory()->recording()->forHotel($this->other->id)->create(['uploaded_by' => $learner->id]);

        $this->assertTrue($learner->can('view', $recording));
        $this->assertTrue(User::factory()->superAdmin()->create()->can('view', $recording));
    }

    public function test_a_hotel_reviewer_opens_only_their_own_hotels_pronunciation_checks()
    {
        $own = PronunciationAttempt::factory()->create(['hotel_id' => $this->home->id]);
        $foreign = PronunciationAttempt::factory()->create(['hotel_id' => $this->other->id]);

        $this->assertTrue($this->reviewer->can('view', $own));
        $this->assertFalse($this->reviewer->can('view', $foreign));
        $this->assertTrue($foreign->user->can('view', $foreign));
        $this->assertTrue(User::factory()->superAdmin()->create()->can('view', $foreign));
    }
}

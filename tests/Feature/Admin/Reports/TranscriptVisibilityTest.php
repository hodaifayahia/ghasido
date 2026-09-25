<?php

namespace Tests\Feature\Admin\Reports;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A spoken answer's transcript and recording belong to whoever holds
 * `transcripts.view` (ROLE-04, PRIV-04, REP-08; spec 0005 §1.2).
 *
 * The Hotel Admin may open Reports & Export for their own hotel, but the
 * Detailed Answers tab and its export carry neither the words nor the
 * recording link for them. The Super Admin still gets both.
 */
class TranscriptVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private const SPOKEN = 'I would like to check in please';

    private function world(): ReportsWorld
    {
        $world = ReportsWorld::build();
        $media = MediaAsset::factory()->recording()->create([
            'uploaded_by' => $world->alice->id,
            'hotel_id' => $world->hotelA->id,
        ]);

        $world->aliceAnswer->forceFill([
            'transcript' => self::SPOKEN,
            'raw_answer' => ['i1' => 'A', 'transcript' => self::SPOKEN],
            'response_media_id' => $media->id,
        ])->save();

        return $world;
    }

    private function hotelAdminOf(ReportsWorld $world): User
    {
        return User::factory()->admin()->create(['hotel_id' => $world->hotelA->id]);
    }

    public function test_the_hotel_admins_answers_export_leaves_out_the_transcript_and_the_recording()
    {
        $world = $this->world();

        $csv = $this->actingAs($this->hotelAdminOf($world))
            ->get(route('reports.export', ['dataset' => 'answers', 'format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        // The row is there, with the same columns, but no spoken words.
        $this->assertStringContainsString('P-ALICE1', $csv);
        $this->assertStringContainsString('Transcript', $csv);
        $this->assertStringNotContainsString(self::SPOKEN, $csv);
        $this->assertStringNotContainsString('/media/', $csv);
    }

    public function test_the_super_admins_answers_export_keeps_them()
    {
        $this->world();

        $csv = $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('reports.export', ['dataset' => 'answers', 'format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString(self::SPOKEN, $csv);
        $this->assertStringContainsString('/media/', $csv);
    }

    public function test_the_hotel_admins_answers_tab_has_no_recording_link()
    {
        $world = $this->world();

        $this->actingAs($this->hotelAdminOf($world))
            ->get(route('reports-export', ['tab' => 'detailedAnswers']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('results.tab', 'detailedAnswers')
                ->where('results.rows.0.audioUrl', null)
                ->etc());
    }
}

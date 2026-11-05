<?php

namespace Tests\Feature\Admin;

use App\Models\AiScenario;
use App\Models\Department;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Client report 2026-11-05: the scenario's first page (the scene, both
 * roles, the objective, the image) was not saved; only the Settings tab
 * was. Each tab's save must keep the other tab's values, and the cover
 * image must be stored on the scenario (RP-01, RP-04, MED-01, MED-07,
 * SEC-04).
 */
class AiScenarioEditorSaveTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config()->set('services.ai.provider', 'fake');
        Storage::fake('public');
        $this->owner = User::factory()->superAdmin()->create();
        $this->department = Department::factory()->create(['name' => 'Reception']);
    }

    /** @return array<string, mixed> */
    private function detailsPayload(): array
    {
        // Exactly what the details tab's Save sends (AiScenariosEditorPanel).
        return [
            'title' => 'Broken air conditioning',
            'department_id' => $this->department->id,
            'difficulty' => 'intermediate',
            'description' => 'A night call about room 305.',
            'situation' => 'A guest calls reception at 11 pm about room 305.',
            'ai_role' => 'A tired business guest who wants a fast solution.',
            'employee_role' => 'The night receptionist.',
            'objective' => 'Solve the problem and keep the guest happy.',
            'opening_line' => 'Hello? My air conditioning is not working!',
            'guest_mood' => 'impatient',
            'goals' => ['Apologise politely', 'Offer a solution'],
            'useful_phrases' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function settingsPayload(): array
    {
        return [
            'attempts_allowed' => 5,
            'feedback_style' => 'balanced',
            'focus_areas' => [['label' => 'Fluency', 'checked' => true], ['label' => 'Grammar', 'checked' => false]],
            'allow_hints' => false,
            'show_suggestions' => true,
            'tags' => ['night-shift'],
        ];
    }

    private function scenario(): AiScenario
    {
        return AiScenario::factory()->create([
            'department_id' => $this->department->id,
            'attempts_allowed' => 3,
        ]);
    }

    private function uploadImage(AiScenario $scenario): MediaAsset
    {
        $this->actingAs($this->owner)
            ->post(route('ai-scenarios.thumbnail.update', $scenario), [
                'image' => UploadedFile::fake()->image('cover.jpg', 1200, 628),
                'alt_text' => 'Cover image for the role-play: Broken air conditioning',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $id = $scenario->fresh()?->thumbnail_media_id;
        $this->assertNotNull($id);

        return MediaAsset::query()->findOrFail($id);
    }

    private function assertDetailsKept(AiScenario $scenario): void
    {
        $scenario->refresh();
        $this->assertSame('Broken air conditioning', $scenario->title);
        $this->assertSame('A night call about room 305.', $scenario->description);
        $this->assertSame('A guest calls reception at 11 pm about room 305.', $scenario->situation);
        $this->assertSame('A tired business guest who wants a fast solution.', $scenario->ai_role);
        $this->assertSame('The night receptionist.', $scenario->employee_role);
        $this->assertSame('Solve the problem and keep the guest happy.', $scenario->objective);
        $this->assertSame(['Apologise politely', 'Offer a solution'], $scenario->goals);
        $this->assertSame('impatient', $scenario->settings['guest_mood'] ?? null);
        $this->assertSame('Hello? My air conditioning is not working!', $scenario->settings['voice_agent']['greeting'] ?? null);
    }

    private function assertSettingsKept(AiScenario $scenario): void
    {
        $scenario->refresh();
        $this->assertSame(5, $scenario->attempts_allowed);
        $this->assertSame('balanced', $scenario->settings['feedback_style'] ?? null);
        $this->assertFalse($scenario->settings['allow_hints'] ?? null);
        $this->assertSame(['night-shift'], $scenario->settings['tags'] ?? null);
    }

    public function test_the_details_tab_saves_on_its_own_and_keeps_the_settings_tab()
    {
        $scenario = $this->scenario();

        // Settings first, as the admin did.
        $this->actingAs($this->owner)
            ->patch(route('ai-scenarios.update', $scenario), [
                ...$this->detailsPayload(),
                'settings' => $this->settingsPayload(),
            ])
            ->assertSessionHasNoErrors();

        // Then the details tab's own save, which sends no settings.
        $this->actingAs($this->owner)
            ->patch(route('ai-scenarios.update', $scenario), [
                ...$this->detailsPayload(),
                'objective' => 'Calm the guest down.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('inertia.flash_data.toast.message', 'Scenario saved.');

        $this->assertSettingsKept($scenario);
        $this->assertSame('Calm the guest down.', $scenario->fresh()?->objective);
    }

    public function test_the_settings_tab_save_keeps_the_details_tab()
    {
        $scenario = $this->scenario();

        $this->actingAs($this->owner)
            ->patch(route('ai-scenarios.update', $scenario), $this->detailsPayload())
            ->assertSessionHasNoErrors();

        // The Settings tab saves through the details form: the same fields
        // plus its own.
        $this->actingAs($this->owner)
            ->patch(route('ai-scenarios.update', $scenario), [
                ...$this->detailsPayload(),
                'settings' => $this->settingsPayload(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDetailsKept($scenario);
        $this->assertSettingsKept($scenario);
    }

    public function test_the_image_is_stored_on_the_scenario_and_shown_after_a_reload()
    {
        $scenario = $this->scenario();
        $asset = $this->uploadImage($scenario);

        Storage::disk('public')->assertExists($asset->path);
        $this->assertSame('Cover image for the role-play: Broken air conditioning', $asset->alt_text);
        $this->assertSame($asset->id, $scenario->fresh()?->thumbnail_media_id);

        $this->actingAs($this->owner)
            ->get(route('ai-scenarios', ['scenario' => $scenario->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('editor.thumbnail.id', $asset->id)
                ->where('editor.thumbnail.url', $asset->url())
                ->where('editor.thumbnailUrl', route('ai-scenarios.thumbnail.update', $scenario)));
    }

    public function test_the_image_survives_a_save_of_either_tab()
    {
        $scenario = $this->scenario();
        $asset = $this->uploadImage($scenario);

        $this->actingAs($this->owner)
            ->patch(route('ai-scenarios.update', $scenario), $this->detailsPayload())
            ->assertSessionHasNoErrors();
        $this->assertSame($asset->id, $scenario->fresh()?->thumbnail_media_id);

        $this->actingAs($this->owner)
            ->patch(route('ai-scenarios.update', $scenario), [
                ...$this->detailsPayload(),
                'settings' => $this->settingsPayload(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($asset->id, $scenario->fresh()?->thumbnail_media_id);
        $this->assertDetailsKept($scenario);
        $this->assertSettingsKept($scenario);
    }

    public function test_the_image_can_be_removed_and_the_file_is_kept()
    {
        $scenario = $this->scenario();
        $asset = $this->uploadImage($scenario);

        $this->actingAs($this->owner)
            ->delete(route('ai-scenarios.thumbnail.destroy', $scenario))
            ->assertRedirect();

        $this->assertNull($scenario->fresh()?->thumbnail_media_id);
        $this->assertModelExists($asset);
    }

    public function test_the_image_is_checked_by_type_and_needs_alt_text()
    {
        $scenario = $this->scenario();

        $this->actingAs($this->owner)
            ->post(route('ai-scenarios.thumbnail.update', $scenario), [
                'image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
                'alt_text' => 'A cover',
            ])
            ->assertSessionHasErrors('image');

        $this->actingAs($this->owner)
            ->post(route('ai-scenarios.thumbnail.update', $scenario), [
                'image' => UploadedFile::fake()->image('cover.png', 600, 314),
            ])
            ->assertSessionHasErrors('alt_text');

        $this->assertNull($scenario->fresh()?->thumbnail_media_id);
    }

    public function test_a_manager_cannot_change_a_scenario_image()
    {
        $scenario = $this->scenario();
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('ai-scenarios.thumbnail.update', $scenario), [
                'image' => UploadedFile::fake()->image('cover.jpg', 600, 314),
                'alt_text' => 'A cover',
            ])
            ->assertForbidden();

        $this->actingAs($manager)
            ->delete(route('ai-scenarios.thumbnail.destroy', $scenario))
            ->assertForbidden();

        $this->assertNull($scenario->fresh()?->thumbnail_media_id);
    }
}

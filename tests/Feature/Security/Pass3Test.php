<?php

namespace Tests\Feature\Security;

use App\Enums\ContentGenerationType;
use App\Enums\GenerationStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\TestAttemptStatus;
use App\Jobs\GenerateContentImage;
use App\Models\ContentGeneration;
use App\Models\Course;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Models\Owner;
use App\Models\PhrasebookItem;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\TextTranslation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Learn\BuildsLearnerFixtures;
use Tests\TestCase;

/**
 * Security audit pass 3 (2026-11-08).
 */
class Pass3Test extends TestCase
{
    use BuildsLearnerFixtures, RefreshDatabase;

    public function test_an_image_cannot_be_aimed_at_a_target_that_does_not_exist_yet()
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->postJson(route('lesson-generations.image'), [
            'prompt' => 'A hotel lobby',
            'target_type' => 'lesson',
            'target_id' => 999999,
        ])->assertForbidden();
    }

    public function test_an_image_job_never_attaches_to_a_target_its_requester_cannot_edit()
    {
        config()->set('services.ai.image_provider', 'fake');
        $editor = User::factory()->admin()->create(['hotel_id' => Hotel::factory()->create()->id]);
        $editor->givePermissionTo(Permission::LessonsManage->value);
        $course = Course::factory()->create(['hotel_id' => Hotel::factory()->create()->id]);
        $foreign = Lesson::factory()->create([
            'unit_id' => Unit::factory()->create(['course_id' => $course->id])->id,
            'cover_media_id' => null,
        ]);
        $this->assertNotNull($foreign->hotel_id);
        $this->assertFalse($editor->can('update', $foreign));

        $generation = ContentGeneration::factory()->create([
            'user_id' => $editor->id,
            'type' => ContentGenerationType::Image,
            'status' => GenerationStatus::Pending,
            'images_total' => 1,
            'target' => ['type' => 'lesson', 'id' => $foreign->id, 'key' => 'image', 'prompt' => 'x', 'alt' => 'x', 'size' => 'landscape'],
        ]);

        GenerateContentImage::dispatchSync($generation->id, null);

        $this->assertNull($foreign->fresh()?->cover_media_id);
    }

    public function test_retrying_a_finished_image_generation_generates_nothing()
    {
        Queue::fake();
        $admin = User::factory()->superAdmin()->create();
        $generation = ContentGeneration::factory()->create([
            'user_id' => $admin->id,
            'type' => ContentGenerationType::Image,
            'status' => GenerationStatus::Done,
            'images_total' => 1,
            'images_done' => 1,
        ]);

        $this->actingAs($admin)->postJson(route('lesson-generations.retry', $generation))->assertStatus(202);

        Queue::assertNotPushed(GenerateContentImage::class);
    }

    public function test_a_report_cannot_name_another_hotels_department()
    {
        $hotel = Hotel::factory()->create();
        $admin = User::factory()->admin()->create(['hotel_id' => $hotel->id]);
        $foreign = Department::factory()->create(['hotel_id' => Hotel::factory()->create()->id]);

        $this->actingAs($admin)
            ->get(route('reports.export', ['dataset' => 'answers', 'format' => 'csv', 'department' => $foreign->id]))
            ->assertForbidden();
    }

    public function test_owner_logout_rotates_the_session_id()
    {
        $owner = Owner::factory()->create();

        $this->actingAs($owner, 'owner');
        $before = session()->getId();

        $this->post(route('owner.logout'))->assertRedirect(route('owner.login'));

        $this->assertNotSame($before, session()->getId());
    }

    public function test_the_phrasebook_shows_no_stored_meaning_during_a_test_without_show_meaning()
    {
        $this->setUpLearnerFixtures();
        $learner = $this->learner();
        $text = 'Where is the swimming pool?';

        TextTranslation::query()->create([
            'hash' => TextTranslation::hashOf($text),
            'locale' => 'ar',
            'source_text' => $text,
            'translation' => 'MEANING-LEAK-MARKER',
            'status' => GenerationStatus::Done,
            'source' => 'ai',
        ]);
        PhrasebookItem::factory()->custom($text, null)->create(['user_id' => $learner->id]);

        $this->actingAs($learner)->get(route('learn.phrasebook'))->assertSee('MEANING-LEAK-MARKER', false);

        $test = Test::factory()->pre()->create([
            'department_id' => $this->department->id,
            'settings' => ['show_meaning' => false, 'time_limit_seconds' => 1200],
        ]);
        TestAttempt::factory()->create([
            'user_id' => $learner->id,
            'test_id' => $test->id,
            'status' => TestAttemptStatus::InProgress,
            'deadline_at' => now()->addMinutes(20),
        ]);

        $this->actingAs($learner)->get(route('learn.phrasebook'))->assertDontSee('MEANING-LEAK-MARKER', false);
    }

    public function test_speech_to_text_is_billed_by_file_size_when_the_claimed_length_is_zero()
    {
        $asset = new MediaAsset(['mime' => 'audio/webm']);
        $asset->size_bytes = 4_000_000;
        $asset->duration_ms = 0;

        $this->assertSame(100_000, $asset->billableDurationMs(0));
        $this->assertSame(120_000, $asset->billableDurationMs(120_000));
    }

    public function test_a_hotel_bound_editor_cannot_rewrite_a_shared_translation()
    {
        $editor = User::factory()->admin()->create(['hotel_id' => Hotel::factory()->create()->id]);
        $editor->givePermissionTo(Permission::LessonsManage->value);

        $this->actingAs($editor)
            ->postJson(route('translations.write'), ['text' => 'Room service', 'arabic' => 'tampered'])
            ->assertForbidden();

        $this->assertSame(0, TextTranslation::query()->count());

        $staff = User::factory()->admin()->create(['hotel_id' => null]);
        $staff->givePermissionTo(Permission::LessonsManage->value);

        $this->actingAs($staff)
            ->postJson(route('translations.write'), ['text' => 'Room service', 'arabic' => 'خدمة الغرف'])
            ->assertOk();
    }

    public function test_a_hotel_bound_delegate_cannot_change_a_system_role()
    {
        $delegate = User::factory()->admin()->create(['hotel_id' => Hotel::factory()->create()->id]);
        $delegate->givePermissionTo(Permission::RolesView->value, Permission::RolesManage->value, Permission::EmployeesView->value);

        $employee = \Spatie\Permission\Models\Role::findByName(Role::Employee->value);

        $this->actingAs($delegate)
            ->put(route('roles.update', $employee), ['permissions' => [Permission::EmployeesView->value]])
            ->assertForbidden();

        $this->actingAs($delegate)
            ->put(route('roles.update', $employee), [])
            ->assertForbidden();
    }
}

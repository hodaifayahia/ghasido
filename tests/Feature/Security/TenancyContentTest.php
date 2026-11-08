<?php

namespace Tests\Feature\Security;

use App\Enums\BlockType;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\AudioClip;
use App\Models\Block;
use App\Models\Course;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\LexiconItem;
use App\Models\MediaAsset;
use App\Models\Test;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Security audit pass 2 (2026-11-08): a hotel-bound role holding a content
 * permission (granted from Roles & Permissions) still cannot move, author,
 * attach or translate content across hotels (ROLE-02, SEC-01).
 */
class TenancyContentTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $home;

    private Hotel $other;

    private Department $department;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->home = Hotel::factory()->create();
        $this->other = Hotel::factory()->create();
        $this->department = Department::factory()->create();
        $this->author = User::factory()->admin()->forHotel($this->home, $this->department)->create();
        $this->author->givePermissionTo(
            Permission::LessonsManage->value,
            Permission::TestsView->value,
            Permission::TestsManage->value,
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_a_lesson_cannot_be_moved_into_another_hotels_unit_through_the_editor()
    {
        $lesson = $this->lessonFor($this->home);
        $foreignUnit = $this->unitFor($this->other);

        $this->actingAs($this->author)
            ->patch(route('lessons.update', $lesson), ['unit_id' => $foreignUnit->id])
            ->assertForbidden();

        $this->assertSame($this->home->id, $lesson->fresh()?->hotel_id);
        $this->assertNotSame($foreignUnit->id, $lesson->fresh()?->unit_id);
    }

    public function test_a_lesson_can_still_move_to_a_unit_of_the_authors_own_hotel()
    {
        $lesson = $this->lessonFor($this->home);
        $ownUnit = $this->unitFor($this->home);

        $this->actingAs($this->author)
            ->patch(route('lessons.update', $lesson), ['unit_id' => $ownUnit->id])
            ->assertRedirect();

        $this->assertSame($ownUnit->id, $lesson->fresh()?->unit_id);
    }

    public function test_a_test_cannot_be_created_for_another_hotel()
    {
        $this->actingAs($this->author)
            ->post(route('tests.store'), $this->newTest(['hotel_id' => $this->other->id]))
            ->assertForbidden();

        $this->assertSame(0, Test::query()->count());
    }

    public function test_a_test_left_shared_by_hotel_staff_belongs_to_their_hotel()
    {
        $this->actingAs($this->author)
            ->post(route('tests.store'), $this->newTest(['hotel_id' => '']))
            ->assertRedirect();

        $this->assertSame($this->home->id, Test::query()->sole()->hotel_id);
    }

    public function test_a_test_cannot_be_handed_to_another_hotel_on_update()
    {
        $test = Test::factory()->create(['hotel_id' => $this->home->id, 'department_id' => $this->department->id]);

        $this->actingAs($this->author)
            ->patch(route('tests.update', $test), $this->settingsPayload(['hotel_id' => $this->other->id]))
            ->assertForbidden();

        $this->assertSame($this->home->id, $test->fresh()?->hotel_id);
    }

    public function test_a_test_update_without_a_hotel_keeps_the_tests_hotel()
    {
        $test = Test::factory()->create(['hotel_id' => $this->home->id, 'department_id' => $this->department->id]);

        $this->actingAs($this->author)
            ->patch(route('tests.update', $test), $this->settingsPayload())
            ->assertRedirect();

        $this->assertSame($this->home->id, $test->fresh()?->hotel_id);
    }

    public function test_a_course_cannot_be_created_for_another_hotel()
    {
        $this->actingAs($this->author)
            ->post(route('courses.store'), [
                'title' => 'Front Desk Basics',
                'department_id' => $this->department->id,
                'hotel_id' => $this->other->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, Course::query()->where('title', 'Front Desk Basics')->count());
    }

    public function test_another_hotels_activity_cannot_be_attached_to_a_lesson_step()
    {
        $block = $this->blockFor($this->home, BlockType::Practice);
        $foreign = Activity::factory()->create(['hotel_id' => $this->other->id]);
        $shared = Activity::factory()->create(['hotel_id' => null]);

        $this->actingAs($this->author)
            ->patch(route('blocks.update', $block), ['activity_ids' => [$shared->id, $foreign->id]])
            ->assertSessionHasErrors('activity_ids');

        $this->assertSame(0, $block->placements()->count());

        $this->actingAs($this->author)
            ->patch(route('blocks.update', $block), ['activity_ids' => [$shared->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame([$shared->id], $block->placements()->pluck('activity_id')->map(fn (mixed $id): int => (int) $id)->all());
    }

    public function test_another_hotels_lexicon_item_cannot_be_attached_to_a_lesson_step()
    {
        $block = $this->blockFor($this->home, BlockType::Vocabulary);
        $foreign = LexiconItem::factory()->create(['hotel_id' => $this->other->id]);

        $this->actingAs($this->author)
            ->patch(route('blocks.update', $block), ['lexicon_item_ids' => [$foreign->id]])
            ->assertSessionHasErrors('lexicon_item_ids');

        $this->assertSame(0, $block->lexiconItems()->count());
    }

    public function test_translations_refuse_another_hotels_content_and_list_only_reachable_content()
    {
        $foreign = $this->lessonFor($this->other);
        $own = $this->lessonFor($this->home);

        $this->actingAs($this->author)
            ->get(route('translations', ['content' => 'lesson:'.$foreign->id]))
            ->assertForbidden();

        $this->actingAs($this->author)
            ->get(route('translations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('options.lessons', fn ($options): bool => collect($options)->pluck('value')->all() === ['lesson:'.$own->id])
            );
    }

    public function test_only_the_super_admin_replaces_a_shared_audio_clip()
    {
        $media = MediaAsset::factory()->audio()->create();
        $payload = ['text' => 'Good morning, how can I help you?', 'speed' => 'normal', 'media_id' => $media->id];

        $this->actingAs($this->author)
            ->post(route('audio.replace'), $payload)
            ->assertForbidden();

        $this->assertSame(0, AudioClip::query()->count());

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('audio.replace'), $payload)
            ->assertRedirect();

        $this->assertSame($media->id, AudioClip::query()->sole()->media_asset_id);
    }

    private function unitFor(Hotel $hotel): Unit
    {
        $course = Course::factory()->forDepartment($this->department->id)->forHotel($hotel->id)->create();

        return Unit::factory()->create(['course_id' => $course->id]);
    }

    private function lessonFor(Hotel $hotel): Lesson
    {
        return Lesson::factory()->create(['unit_id' => $this->unitFor($hotel)->id]);
    }

    private function blockFor(Hotel $hotel, BlockType $type): Block
    {
        return Block::factory()->ofType($type)->create(['lesson_id' => $this->lessonFor($hotel)->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function newTest(array $overrides = []): array
    {
        return [
            'title' => 'Reception Pre-test',
            'type' => 'pre',
            'department_id' => $this->department->id,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function settingsPayload(array $overrides = []): array
    {
        return [
            'title' => 'Reception Pre-test',
            'type' => 'pre',
            'department_id' => $this->department->id,
            'results_visibility' => 'score',
            ...$overrides,
        ];
    }
}

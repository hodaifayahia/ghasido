<?php

namespace Tests\Feature\Admin\Lessons;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\LexiconItem;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The boundary on every CMS route (ROLE-01, ROLE-02, SEC-01): a manager
 * holds lessons.view only, so every write is a 403 — never a 404, never a
 * redirect — and even with the manage capability granted to the role,
 * another hotel's content stays out of reach.
 */
class LessonsCrossTenantTest extends TestCase
{
    use BuildsContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildContent();
        Storage::fake('public');
    }

    /**
     * Every write route with a valid payload, keyed by name.
     *
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private function writes(): array
    {
        $block = $this->lesson->blocks()->firstOrFail();
        $item = LexiconItem::factory()->create();
        $activity = Activity::factory()->create();

        return [
            'courses.store' => ['post', route('courses.store'), ['title' => 'X', 'department_id' => $this->department->id]],
            'courses.update' => ['patch', route('courses.update', $this->course), ['title' => 'Y']],
            'courses.archive' => ['post', route('courses.archive', $this->course), []],
            'units.store' => ['post', route('units.store'), ['course_id' => $this->course->id, 'title' => 'U']],
            'units.update' => ['patch', route('units.update', $this->unit), ['title' => 'V']],
            'lessons.store' => ['post', route('lessons.store'), ['unit_id' => $this->unit->id, 'title' => 'L']],
            'lessons.update' => ['patch', route('lessons.update', $this->lesson), ['title' => 'M']],
            'lessons.publish' => ['post', route('lessons.publish', $this->lesson), []],
            'lessons.duplicate' => ['post', route('lessons.duplicate', $this->lesson), []],
            'lessons.archive' => ['post', route('lessons.archive', $this->lesson), []],
            'blocks.store' => ['post', route('blocks.store', $this->lesson), ['type' => 'note']],
            'blocks.update' => ['patch', route('blocks.update', $block), ['title' => 'B']],
            'blocks.reorder' => ['put', route('blocks.reorder', $this->lesson), ['order' => $this->lesson->blocks()->pluck('id')->all()]],
            'blocks.duplicate' => ['post', route('blocks.duplicate', $block), []],
            'blocks.toggle' => ['post', route('blocks.toggle', $block), []],
            'blocks.destroy' => ['delete', route('blocks.destroy', $block), []],
            'lexicon.store' => ['post', route('lexicon.store'), ['kind' => 'word', 'english_text' => 'Towel']],
            'lexicon.update' => ['patch', route('lexicon.update', $item), ['ipa' => 'x']],
            'lexicon.generate' => ['post', route('lexicon.generate', $item), []],
            'lexicon.apply-draft' => ['post', route('lexicon.apply-draft', $item), []],
            'lexicon.audio' => ['post', route('lexicon.audio', $item), []],
            'activities.store' => ['post', route('activities.store'), ['type' => 'writing', 'prompt' => 'W', 'payload' => ['items' => [['id' => 'i1']]]]],
            'activities.update' => ['patch', route('activities.update', $activity), ['prompt' => 'Z']],
            'media.store' => ['post', route('media.store'), ['file' => UploadedFile::fake()->image('a.jpg'), 'alt_text' => 'a']],
        ];
    }

    public function test_a_manager_may_read_the_screen_but_every_write_is_refused()
    {
        $manager = $this->manager();

        $this->actingAs($manager)
            ->get(route('lessons-content'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('editor.title', 'Greeting Guests'));

        $this->actingAs($manager)->get(route('lessons.preview', $this->lesson))->assertOk();

        foreach ($this->writes() as $name => [$verb, $url, $payload]) {
            $this->actingAs($manager)->{$verb}($url, $payload)->assertForbidden();
        }

        $this->assertSame(0, AuditLog::count());
        $this->assertSame('Greeting Guests', $this->lesson->fresh()?->title);
    }

    public function test_a_manager_holding_the_manage_capability_still_cannot_touch_another_hotels_content()
    {
        RoleModel::findByName(Role::Manager->value)->givePermissionTo(Permission::LessonsManage->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $theirs = Hotel::factory()->create(['name' => 'Elsewhere']);
        $theirCourse = Course::factory()->forDepartment($this->department->id)->forHotel($theirs->id)->create(['title' => 'Private']);
        $theirUnit = Unit::factory()->create(['course_id' => $theirCourse->id]);
        $theirLesson = Lesson::factory()->withBlocks()->create(['unit_id' => $theirUnit->id]);
        $theirBlock = $theirLesson->blocks()->firstOrFail();
        $theirItem = LexiconItem::factory()->create(['hotel_id' => $theirs->id]);
        $theirActivity = Activity::factory()->create(['hotel_id' => $theirs->id]);

        $manager = $this->manager();

        $refused = [
            ['get', route('lessons.preview', $theirLesson), []],
            ['patch', route('courses.update', $theirCourse), ['title' => 'Y']],
            ['post', route('units.store'), ['course_id' => $theirCourse->id, 'title' => 'U']],
            ['patch', route('units.update', $theirUnit), ['title' => 'V']],
            ['post', route('lessons.store'), ['unit_id' => $theirUnit->id, 'title' => 'L']],
            ['patch', route('lessons.update', $theirLesson), ['title' => 'M']],
            ['post', route('lessons.publish', $theirLesson), []],
            ['post', route('blocks.store', $theirLesson), ['type' => 'note']],
            ['patch', route('blocks.update', $theirBlock), ['title' => 'B']],
            ['delete', route('blocks.destroy', $theirBlock), []],
            ['post', route('lexicon.store'), ['kind' => 'word', 'english_text' => 'T', 'block_id' => $theirBlock->id]],
            ['patch', route('lexicon.update', $theirItem), ['ipa' => 'x']],
            ['post', route('lexicon.audio', $theirItem), []],
            ['patch', route('activities.update', $theirActivity), ['prompt' => 'Z']],
        ];

        foreach ($refused as [$verb, $url, $payload]) {
            $this->actingAs($manager)->{$verb}($url, $payload)->assertForbidden();
        }

        // The other hotel's course never reaches their tree either.
        $this->actingAs($manager)
            ->get(route('lessons-content', ['hotel' => $theirs->id, 'course' => $theirCourse->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.hotel', (string) $this->hotel->id)
                ->has('courses', 1)
                ->where('courses.0.title', 'Guest Service Basics')
            );

        // Shared content, on the other hand, is theirs to edit now.
        $this->actingAs($manager)
            ->patch(route('lessons.update', $this->lesson), ['title' => 'Edited by the manager'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Edited by the manager', $this->lesson->fresh()?->title);
        $this->assertSame(0, AuditLog::query()->where('auditable_id', $theirLesson->id)->count());
    }

    public function test_an_employee_is_refused_the_whole_screen()
    {
        $employee = User::factory()->employee()->forHotel($this->hotel, $this->department)->create();

        $this->actingAs($employee)->get(route('lessons-content'))->assertForbidden();
        $this->actingAs($employee)->get(route('lessons.preview', $this->lesson))->assertForbidden();
        $this->actingAs($employee)->post(route('blocks.store', $this->lesson), ['type' => 'note'])->assertForbidden();
    }
}

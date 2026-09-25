<?php

namespace Tests\Feature\Admin\Lessons;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Lessons & Content page reads real rows and follows the query string
 * (CMS-01, CMS-03, CMS-04, BLD-01, BLD-02, MED-02; spec 0003 Part D).
 */
class ContentTreeTest extends TestCase
{
    use BuildsContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildContent();
    }

    public function test_the_tree_loads_the_course_its_unit_and_the_active_lesson()
    {
        $this->actingAs($this->owner)
            ->get(route('lessons-content'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/LessonsContent')
                ->where('filters.hotel', (string) $this->hotel->id)
                ->where('filters.department', (string) $this->department->id)
                ->where('filters.course', (string) $this->course->id)
                ->where('filters.unit', (string) $this->unit->id)
                ->where('filters.lesson', (string) $this->lesson->id)
                ->where('filters.hotels.0.value', 'shared')
                ->where('filters.units.0.label', '1. Welcoming Guests')
                ->where('filters.lessons.0.label', '1. Greeting Guests')
                ->where('activeTab', 'content')
                ->has('lessonDirectory', 1)
                ->where('lessonDirectory.0.title', 'Greeting Guests')
                ->where('lessonDirectory.0.department', 'Reception')
                ->where('lessonDirectory.0.hotel', 'All hotels')
                ->where('lessonDirectory.0.status', 'draft')
                ->where('lessonDirectory.0.steps', 9)
                ->where('builderOpen', false)
                ->has('courses', 1)
                ->where('courses.0.title', 'Guest Service Basics')
                ->where('courses.0.tone', 'brand')
                ->where('courses.0.expanded', true)
                ->where('courses.0.units.0.title', 'Unit 1: Welcoming Guests')
                ->where('courses.0.units.0.expanded', true)
                ->where('courses.0.units.0.lessons.0.title', '1. Greeting Guests')
                ->where('courses.0.units.0.lessons.0.active', true)
                ->where('courses.0.units.0.addLessonLabel', '+ Add Lesson')
                ->where('editor.id', $this->lesson->id)
                ->where('editor.title', 'Greeting Guests')
                ->where('editor.titleCount', '15/100')
                ->where('editor.status', 'draft')
                ->has('editor.objectives', 3)
                ->has('blocks', 12)
                ->where('blocks.0.type', 'situation')
                ->where('blocks.10.type', null)
                ->has('lessonBlocks', 9)
                ->where('lessonBlocks.0.type', 'situation')
                ->where('lessonBlocks.0.position', 1)
                ->where('lessonBlocks.8.type', 'complete')
                ->has('blockTypes', 15)
            );
    }

    public function test_lesson_creation_starts_with_a_department_and_has_no_hotel_scope_step()
    {
        $this->actingAs($this->owner)
            ->get(route('lessons-content.create', ['department' => $this->department->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/LessonsCreate')
                ->where('departmentId', (string) $this->department->id)
                ->where('departmentName', 'Reception')
                ->has('departments', 1)
                ->has('courses', 1)
                ->where('courses.0.title', 'Guest Service Basics')
                ->where('courses.0.units.0.title', 'Welcoming Guests')
                ->missing('hotel')
                ->missing('hotelScope')
            );
    }

    public function test_editing_a_directory_lesson_opens_the_dedicated_builder_page()
    {
        $this->actingAs($this->owner)
            ->get(route('lessons.edit', $this->lesson))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/LessonsContent')
                ->where('builderOpen', true)
                ->where('filters.lesson', (string) $this->lesson->id)
                ->where('editor.id', $this->lesson->id)
            );
    }

    public function test_the_query_string_selects_hotel_department_course_unit_lesson_and_tab()
    {
        $other = Course::factory()->forDepartment($this->department->id)->create(['title' => 'Telephone English', 'tone' => 'danger', 'position' => 2]);
        $unit = Unit::factory()->create(['course_id' => $other->id, 'title' => 'Calls']);
        $lesson = Lesson::factory()->create(['unit_id' => $unit->id, 'title' => 'Answering the Phone']);

        $this->actingAs($this->owner)
            ->get(route('lessons-content', [
                'hotel' => 'shared',
                'department' => $this->department->id,
                'course' => $other->id,
                'unit' => $unit->id,
                'lesson' => $lesson->id,
                'tab' => 'settings',
                'open' => 'c'.$this->course->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.hotel', 'shared')
                ->where('filters.course', (string) $other->id)
                ->where('activeTab', 'settings')
                ->has('courses', 2)
                ->where('courses.0.expanded', true)
                ->where('courses.1.expanded', true)
                ->where('courses.1.units.0.lessons.0.active', true)
                ->where('editor.title', 'Answering the Phone')
                ->where('builderOpen', true)
                ->where('editor.hotelLabel', 'Shared (all hotels)')
            );
    }

    public function test_a_hotel_specific_course_shows_only_under_its_hotel()
    {
        $private = Course::factory()->forDepartment($this->department->id)->forHotel($this->hotel->id)->create(['title' => 'House Style']);

        $this->actingAs($this->owner)
            ->get(route('lessons-content', ['hotel' => 'shared']))
            ->assertInertia(fn (Assert $page) => $page->has('courses', 1));

        $this->actingAs($this->owner)
            ->get(route('lessons-content', ['hotel' => $this->hotel->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('courses', 2)
                ->where('courses.1.title', $private->title)
            );
    }

    public function test_the_lesson_directory_filters_and_paginates_from_the_query_string()
    {
        Lesson::factory()->published()->count(11)->create(['unit_id' => $this->unit->id]);

        $this->actingAs($this->owner)
            ->get(route('lessons-content', [
                'directorySearch' => 'Greeting',
                'directoryStatus' => 'draft',
                'directoryPerPage' => 20,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('directoryFilters.search', 'Greeting')
                ->where('directoryFilters.status', 'draft')
                ->where('directoryPagination.perPage', 20)
                ->where('directoryPagination.total', 1)
                ->where('directoryPagination.from', 1)
                ->where('directoryPagination.to', 1)
                ->where('lessonDirectory.0.title', 'Greeting Guests')
                ->has('directoryStats', 4)
            );
    }

    public function test_the_image_library_lists_real_media_rows_by_tab_category_and_search()
    {
        MediaAsset::factory()->count(3)->create(['library' => 'my_images', 'category' => 'reception', 'label' => 'desk']);
        MediaAsset::factory()->create(['library' => 'my_images', 'category' => 'people', 'label' => 'smile']);
        MediaAsset::factory()->seed('lobby')->create(['category' => 'hotel']);

        $this->actingAs($this->owner)
            ->get(route('lessons-content'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('library.activeTab', 'my-images')
                ->where('library.total', 4)
                ->has('library.images', 4)
                ->has('library.categories', 4)
            );

        $this->actingAs($this->owner)
            ->get(route('lessons-content', ['lib' => 'my-images', 'libCategory' => 'people']))
            ->assertInertia(fn (Assert $page) => $page->where('library.total', 1)->where('library.images.0.label', 'smile'));

        $this->actingAs($this->owner)
            ->get(route('lessons-content', ['lib' => 'guesvia-library', 'libSearch' => 'lob']))
            ->assertInertia(fn (Assert $page) => $page->where('library.total', 1)->where('library.images.0.label', 'lobby'));

        $this->actingAs($this->owner)
            ->getJson(route('media.index', ['lib' => 'my-images']))
            ->assertOk()
            ->assertJsonPath('total', 4);
    }

    public function test_the_preview_renders_the_employee_step_page_with_preview_links()
    {
        $this->actingAs($this->owner)
            ->get(route('lessons.preview', $this->lesson))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employee/lesson/Step')
                ->where('preview', true)
                ->where('block.type', 'situation')
                ->has('steps', 9)
                ->where('steps.1.url', route('lessons.preview', ['lesson' => $this->lesson, 'block' => $this->lesson->blocks()->get()[1]]))
                ->where('prevUrl', null)
            );

        $first = $this->lesson->blocks()->first();

        $this->actingAs($this->owner)
            ->post(route('lessons.preview.next', ['lesson' => $this->lesson, 'block' => $first]))
            ->assertRedirect(route('lessons.preview', ['lesson' => $this->lesson, 'block' => $this->lesson->blocks()->get()[1]]));

        $this->assertDatabaseCount('block_completions', 0);
    }
}

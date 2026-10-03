<?php

namespace Tests\Feature\Admin\Lessons;

use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Client report 2026-10-02: "the manager can see the lesson I am still
 * working on". A manager reads lessons but never builds them, so only
 * published lessons (their hotel's or shared) reach them; the Super Admin
 * still sees every draft.
 */
class ManagerSeesPublishedLessonsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manager_sees_published_lessons_only()
    {
        $this->withoutVite();
        $hotel = Hotel::factory()->active()->create();
        $manager = User::factory()->manager()->create(['hotel_id' => $hotel->id]);
        $published = Lesson::factory()->published()->create(['title' => 'Live lesson']);
        $draft = Lesson::factory()->create(['title' => 'Draft lesson']);

        $this->actingAs($manager)
            ->get(route('lessons-content'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('lessonDirectory', 1)
                ->where('lessonDirectory.0.id', $published->id));

        $this->actingAs($manager)
            ->get(route('lessons.edit', $draft))
            ->assertForbidden();

        $owner = User::factory()->superAdmin()->create();

        $this->actingAs($owner)
            ->get(route('lessons-content'))
            ->assertInertia(fn (Assert $page) => $page->has('lessonDirectory', 2));
    }
}

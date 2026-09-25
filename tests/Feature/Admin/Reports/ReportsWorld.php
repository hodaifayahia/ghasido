<?php

namespace Tests\Feature\Admin\Reports;

use App\Enums\AccountStatus;
use App\Models\Activity;
use App\Models\AiScenario;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\RoleplayAttempt;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\Unit;
use App\Models\User;

/**
 * A small, fully known world for the report tests: two hotels, one shared
 * Reception curriculum (two lessons, a paired Pre/Post test, one scenario)
 * and three employees at different points of the journey.
 *
 *  - Alice (hotel A): Pre 60 %, Post 85 %, both lessons done, one completed
 *    role-play, active yesterday → "completed".
 *  - Bob (hotel A): Pre 40 %, one lesson done, active 10 days ago → "in progress".
 *  - Carol (hotel B): Pre 80 %, nothing else, active 45 days ago → "inactive".
 */
final class ReportsWorld
{
    public Hotel $hotelA;

    public Hotel $hotelB;

    public Department $reception;

    public Course $course;

    /** @var list<Lesson> */
    public array $lessons = [];

    public Test $pre;

    public Test $post;

    public AiScenario $scenario;

    public Activity $question;

    public User $alice;

    public User $bob;

    public User $carol;

    public TestAttempt $alicePre;

    public Attempt $aliceAnswer;

    public RoleplayAttempt $aliceRoleplay;

    public static function build(): self
    {
        $world = new self;

        $world->hotelA = Hotel::factory()->create(['name' => 'Hotel Alpha']);
        $world->hotelB = Hotel::factory()->create(['name' => 'Hotel Beta']);
        $world->reception = Department::factory()->create(['name' => 'Reception', 'slug' => 'reception', 'position' => 1]);

        $world->course = Course::factory()->published()->forDepartment($world->reception->id)->create(['title' => 'Guest Service Basics']);
        $unit = Unit::factory()->create(['course_id' => $world->course->id]);

        foreach (['Handling Guest Complaints', 'Greeting Guests'] as $position => $title) {
            $world->lessons[] = Lesson::factory()->published()->at($position + 1)->create(['unit_id' => $unit->id, 'title' => $title]);
        }

        $world->pre = Test::factory()->pre()->create(['department_id' => $world->reception->id, 'title' => 'Reception Pre-test']);
        $world->post = Test::factory()->post()->create(['department_id' => $world->reception->id, 'title' => 'Reception Post-test', 'paired_test_id' => $world->pre->id]);
        $world->scenario = AiScenario::factory()->published()->forDepartment($world->reception->id)->create(['title' => 'Check-in']);
        $world->question = Activity::factory()->create(['skill_label' => 'Situation']);

        $world->alice = self::employee($world->hotelA, $world->reception, 'Alice Amrani', 'P-ALICE1', now()->subDay());
        $world->bob = self::employee($world->hotelA, $world->reception, 'Bob Benali', 'P-BOB001', now()->subDays(10));
        $world->carol = self::employee($world->hotelB, $world->reception, 'Carol Cherif', 'P-CAROL1', now()->subDays(45));

        // Alice: Pre 15/25, Post 17/20, both lessons, one completed role-play.
        $world->alicePre = self::sitting($world->alice, $world->pre, 15, 25, now()->subDays(20), ['Situation' => ['correct' => 3, 'total' => 5, 'ungraded' => 0]]);
        self::sitting($world->alice, $world->post, 17, 20, now()->subDays(2), ['Situation' => ['correct' => 5, 'total' => 5, 'ungraded' => 0]]);

        $world->aliceAnswer = Attempt::factory()->inTest($world->alicePre)->create([
            'activity_id' => $world->question->id,
            'raw_answer' => ['i1' => 'A'],
            'is_correct' => true,
            'score' => 1,
            'max_score' => 1,
            'time_taken_ms' => 12345,
            'submitted_at' => now()->subDays(20),
        ]);

        foreach ($world->lessons as $index => $lesson) {
            LessonCompletion::factory()->create([
                'user_id' => $world->alice->id,
                'lesson_id' => $lesson->id,
                'completed_at' => now()->subDays(15 - $index),
            ]);
        }

        $world->aliceRoleplay = RoleplayAttempt::factory()->completed()->create([
            'user_id' => $world->alice->id,
            'ai_scenario_id' => $world->scenario->id,
            'started_at' => now()->subDays(3),
            'ended_at' => now()->subDays(3)->addMinutes(3),
        ]);

        // Bob: Pre 10/25, first lesson only.
        self::sitting($world->bob, $world->pre, 10, 25, now()->subDays(12), ['Situation' => ['correct' => 2, 'total' => 5, 'ungraded' => 0]]);
        LessonCompletion::factory()->create([
            'user_id' => $world->bob->id,
            'lesson_id' => $world->lessons[0]->id,
            'completed_at' => now()->subDays(10),
        ]);

        // Carol: Pre 20/25, nothing since.
        self::sitting($world->carol, $world->pre, 20, 25, now()->subDays(45), ['Situation' => ['correct' => 4, 'total' => 5, 'ungraded' => 0]]);

        return $world;
    }

    public static function employee(Hotel $hotel, Department $department, string $name, string $code, \DateTimeInterface $lastActivity): User
    {
        return User::factory()
            ->employee()
            ->forHotel($hotel, $department)
            ->withUsername(strtolower(str_replace(' ', '.', $name)))
            ->create([
                'name' => $name,
                'participant_code' => $code,
                'status' => AccountStatus::Active,
                'last_activity_at' => $lastActivity,
                'training_started_at' => now()->subDays(30),
            ]);
    }

    /**
     * @param  array<string, array{correct: int, total: int, ungraded: int}>  $breakdown
     */
    public static function sitting(User $user, Test $test, float $score, float $max, \DateTimeInterface $submittedAt, array $breakdown): TestAttempt
    {
        return TestAttempt::factory()->submitted($score, $max)->create([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'started_at' => (clone $submittedAt)->modify('-15 minutes'),
            'submitted_at' => $submittedAt,
            'breakdown' => $breakdown,
        ]);
    }

    public function managerOfA(): User
    {
        return User::factory()->manager()->create([
            'hotel_id' => $this->hotelA->id,
            'status' => AccountStatus::Active,
        ]);
    }
}

<?php

namespace Tests\Feature\Seeders;

use App\Enums\ActivityType;
use App\Enums\BlockType;
use App\Enums\GenerationStatus;
use App\Enums\RoleplayStatus;
use App\Enums\TestAttemptStatus;
use App\Enums\TestType;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\ActivityVersion;
use App\Models\AiScenario;
use App\Models\Attempt;
use App\Models\AudioClip;
use App\Models\AutomationRule;
use App\Models\Block;
use App\Models\Course;
use App\Models\Department;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\LexiconItem;
use App\Models\MediaAsset;
use App\Models\Reminder;
use App\Models\ReminderTemplate;
use App\Models\RoleplayAttempt;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LearningContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The learning content the employee journey is built on (spec 0003 Part G).
 *
 * Seeds the way `migrate:fresh --seed` does, through DatabaseSeeder, so the
 * WithoutModelEvents wrapper that mutes nested seeders is part of the test:
 * the content models' hooks (activity versions, lesson denormalisation, clip
 * hashes) must still have fired.
 */
class LearningContentSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config()->set('services.tts.provider', 'fake');
    }

    public function test_it_seeds_the_course_the_mockups_describe()
    {
        $this->seed(DatabaseSeeder::class);

        $course = Course::query()->where('slug', LearningContentSeeder::COURSE_SLUG)->firstOrFail();
        $this->assertSame('Guest Service Basics', $course->title);
        $this->assertSame(8, $course->lessons()->count(), 'Guest Service Basics has eight lessons (G.2).');
        $this->assertSame(5, $course->units()->count());
        $this->assertSame(6, Course::query()->count(), 'One main course plus the five tree courses (G.2).');

        // G.1: every catalogue department carries its focus line.
        $this->assertSame(0, Department::query()->whereNull('hotel_id')->whereNull('focus')->count());

        // G.3: nine visible blocks, in mockup order.
        $lesson = Lesson::query()->where('slug', LearningContentSeeder::LESSON_SLUG)->firstOrFail();
        $this->assertSame($course->id, $lesson->course_id, 'The lesson saving hook denormalised course_id.');
        $this->assertSame(
            [
                BlockType::Situation, BlockType::Vocabulary, BlockType::Expressions, BlockType::ListenRepeat,
                BlockType::Dialogue, BlockType::Video, BlockType::Practice, BlockType::AiRoleplay, BlockType::Complete,
            ],
            $lesson->visibleBlocks()->orderBy('position')->pluck('type')->all(),
        );

        $vocabulary = $lesson->blocks()->where('type', BlockType::Vocabulary->value)->firstOrFail();
        $this->assertSame('Air conditioning', $vocabulary->lexiconItems()->first()?->english_text);
        $this->assertSame(6, $vocabulary->lexiconItems()->count());
        $this->assertSame($vocabulary->lexiconItems()->first()?->id, $vocabulary->setting('featured_lexicon_item_id'));

        $expressions = $lesson->blocks()->where('type', BlockType::Expressions->value)->firstOrFail();
        $this->assertSame("I'm sorry for the inconvenience.", $expressions->lexiconItems()->first()?->english_text);
        $this->assertSame(6, $expressions->lexiconItems()->count());

        // The seven practice activities, hub order, one item set each.
        $practice = $lesson->blocks()->where('type', BlockType::Practice->value)->firstOrFail();
        $this->assertSame(
            ['listen_choose', 'look_listen', 'best_response', 'listen_match', 'watch_respond', 'words_sentences', 'dialogue_order'],
            $practice->placements()->with('activity')->get()->map(fn (ActivityPlacement $p) => $p->activity?->type->value)->all(),
        );

        // The role-play block offers the six scenarios.
        $roleplay = $lesson->blocks()->where('type', BlockType::AiRoleplay->value)->firstOrFail();
        $this->assertCount(6, $roleplay->scenarioIds());
        $this->assertSame(6, AiScenario::query()->count());
        $this->assertNotNull(AiScenario::query()->where('slug', 'check-in')->first(), 'FakeAiProvider keys its script on this slug.');

        // Media rows exist once per manifest path, public disk, seed library.
        $this->assertSame(58, MediaAsset::query()->where('library', 'seed')->count());
        $this->assertSame(0, MediaAsset::query()->where('library', 'seed')->where('disk', '!=', 'public')->count());
        $this->assertNotNull($lesson->cover_media_id);
    }

    public function test_every_activity_has_its_first_version_and_the_tests_are_paired()
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, Activity::query()->count());
        $this->assertSame(
            Activity::query()->count(),
            ActivityVersion::query()->where('version', 1)->count(),
            'Every activity was created through the model, so v1 exists (DATA-11).',
        );

        $pre = Test::query()->ofType(TestType::Pre)->firstOrFail();
        $post = Test::query()->ofType(TestType::Post)->firstOrFail();

        $this->assertSame(25, $pre->questions()->count());
        $this->assertSame(25, $post->questions()->count());
        $this->assertSame($post->id, $pre->paired_test_id);
        $this->assertSame($pre->id, $post->paired_test_id);
        $this->assertSame(1200, $pre->timeLimitSeconds());
        $this->assertSame('score', $pre->resultsVisibility()->value);
        $this->assertSame("Let's start with a short Pre-test", $pre->intro['heading'] ?? null);

        // Photo_21: question 8 is exactly the mockup question.
        $eighth = $pre->questions()->where('position', 8)->with('activity')->firstOrFail()->activity;
        $this->assertNotNull($eighth);
        $this->assertSame(ActivityType::MultipleChoice, $eighth->type);
        $this->assertSame('Situation', $eighth->skill_label);
        $this->assertSame('Look at the situation and choose the best response.', $eighth->prompt);
        $this->assertSame('Good evening! Can I help you with your luggage?', $eighth->items()[0]['options'][0]['text'] ?? null);
        $this->assertFalse($eighth->show_meaning_enabled, 'No Show Meaning inside a test (CTRL-04).');

        // Photo_24: question 18 is the speaking question.
        $eighteenth = $pre->questions()->where('position', 18)->with('activity')->firstOrFail()->activity;
        $this->assertSame(ActivityType::Speaking, $eighteenth?->type);
    }

    public function test_it_generates_audio_for_every_playable_sentence()
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(300, AudioClip::query()->count());
        $this->assertSame(
            0,
            AudioClip::query()->where('status', '!=', GenerationStatus::Done->value)->count(),
            'With a sync queue and the fake TTS every clip is written during seeding.',
        );

        // Both speeds of a lexicon word and of a dialogue line exist.
        $this->assertSame(2, AudioClip::query()->where('text_hash', AudioClip::hashFor('Air conditioning'))->count());
        $this->assertSame(2, AudioClip::query()->where('text_hash', AudioClip::hashFor("Good afternoon.\nHow can I help you?"))->count());
        $this->assertSame(2, AudioClip::query()->where('text_hash', AudioClip::hashFor('Hello! I have a reservation for tonight.'))->count());

        $clip = AudioClip::query()->where('text_hash', AudioClip::hashFor('Towel'))->firstOrFail();
        $this->assertNotNull($clip->mediaAsset);
        Storage::disk('public')->assertExists($clip->mediaAsset->path);
    }

    public function test_samira_and_amine_are_in_the_states_the_journey_needs()
    {
        $this->seed(DatabaseSeeder::class);

        $samira = User::query()->where('username', 'samira')->firstOrFail();
        $this->assertSame('Samira Benali', $samira->name);
        $this->assertSame("La Gazelle d'Or", $samira->hotel?->name);
        $this->assertSame('Reception', $samira->department?->name);
        $this->assertTrue($samira->hasRole('employee'));
        $this->assertTrue($samira->hasCompletedFirstLogin());
        $this->assertTrue($samira->consentsToReminders());
        $this->assertFalse($samira->hasSubmittedPreTest(), 'Samira has not sat the pre-test, so /learn shows the intro (photo_20).');
        $this->assertNotNull($samira->participant_code);
        $this->assertTrue(password_verify('password', $samira->password));

        $amine = User::query()->where('username', 'amine')->firstOrFail();
        $this->assertTrue($amine->hasSubmittedPreTest());

        $sitting = TestAttempt::query()->where('user_id', $amine->id)->firstOrFail();
        $this->assertSame(TestAttemptStatus::Submitted, $sitting->status);
        $this->assertSame(25, Attempt::query()->where('test_attempt_id', $sitting->id)->count(), 'One answer row per question (TEST-06).');
        $this->assertSame(0, Attempt::query()->where('test_attempt_id', $sitting->id)->whereNull('raw_answer')->count());
        $this->assertSame(0, Attempt::query()->where('test_attempt_id', $sitting->id)->whereNull('time_taken_ms')->count(), 'Time is always recorded (DATA-08).');
        $this->assertNotNull($sitting->score);
        $this->assertSame(23.0, (float) $sitting->max_score, 'Two speaking questions are ungraded.');

        $lesson = Lesson::query()->where('slug', LearningContentSeeder::LESSON_SLUG)->firstOrFail();
        $this->assertTrue(LessonCompletion::query()->where('user_id', $amine->id)->where('lesson_id', $lesson->id)->exists());
        $this->assertSame(9, $amine->blockCompletions()->where('lesson_id', $lesson->id)->count());

        $attempt = RoleplayAttempt::query()->where('user_id', $amine->id)->firstOrFail();
        $this->assertSame(RoleplayStatus::Completed, $attempt->status);
        $this->assertSame('check-in', $attempt->scenario?->slug);
        $this->assertSame(70, $attempt->overall_score);
        $this->assertSame('Good try!', $attempt->feedback['summary_label'] ?? null);
        $this->assertSame('Hello! I have a reservation for tonight.', $attempt->transcript[0]['text'] ?? null);
        $this->assertCount(7, $attempt->transcript);

        // Seat numbers are untouched: samira and amine are renamed portfolio seats.
        $this->assertSame(5, User::query()->where('hotel_id', $samira->hotel_id)->where('department_id', $samira->department_id)->count());
    }

    public function test_the_portfolio_carries_progress_usernames_and_reminders()
    {
        $this->seed(DatabaseSeeder::class);

        $employees = User::query()->employees()->get();
        $this->assertGreaterThan(150, $employees->count());
        $this->assertSame(0, $employees->whereNull('username')->count());
        $this->assertSame(0, $employees->whereNull('participant_code')->count());
        $this->assertSame($employees->count(), $employees->unique('username')->count());
        $this->assertGreaterThan(0, $employees->whereNull('last_activity_at')->count());
        $this->assertGreaterThan(0, $employees->whereNotNull('email_consent_at')->count());
        $this->assertGreaterThan(0, $employees->whereNull('email_consent_at')->count());

        $this->assertGreaterThan(10, TestAttempt::query()->count());
        $this->assertGreaterThan(20, LessonCompletion::query()->count());
        $this->assertGreaterThan(1, RoleplayAttempt::query()->count());
        $this->assertGreaterThan(0, TestAttempt::query()->whereHas('test', fn ($q) => $q->where('type', TestType::Post->value))->count());

        $this->assertSame(3, ReminderTemplate::query()->count());
        $this->assertSame(2, AutomationRule::query()->count());
        $this->assertGreaterThanOrEqual(10, Reminder::query()->count());
        $this->assertGreaterThan(0, Reminder::query()->where('status', 'sent')->count());
        $this->assertGreaterThan(0, Reminder::query()->where('status', 'blocked')->count());
        $this->assertGreaterThan(0, Reminder::query()->where('status', 'scheduled')->count());

        $this->assertNotNull(User::query()->where('email', 'harness@guesvia.test')->first()?->hasRole('super_admin'));
    }

    public function test_seeding_twice_leaves_one_of_everything()
    {
        $this->seed(DatabaseSeeder::class);

        $counts = $this->counts();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($counts, $this->counts());
        $this->assertSame(1, User::query()->where('username', 'samira')->count());
        $this->assertSame(1, Block::query()->where('type', BlockType::AiRoleplay->value)->count());
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'courses' => Course::query()->count(),
            'lessons' => Lesson::query()->count(),
            'blocks' => Block::query()->count(),
            'lexicon' => LexiconItem::query()->count(),
            'activities' => Activity::query()->count(),
            'versions' => ActivityVersion::query()->count(),
            'placements' => ActivityPlacement::query()->count(),
            'tests' => Test::query()->count(),
            'scenarios' => AiScenario::query()->count(),
            'media' => MediaAsset::query()->count(),
            'clips' => AudioClip::query()->count(),
            'users' => User::query()->count(),
            'test_attempts' => TestAttempt::query()->count(),
            'attempts' => Attempt::query()->count(),
            'roleplays' => RoleplayAttempt::query()->count(),
            'lesson_completions' => LessonCompletion::query()->count(),
            'templates' => ReminderTemplate::query()->count(),
            'rules' => AutomationRule::query()->count(),
            'reminders' => Reminder::query()->count(),
        ];
    }
}

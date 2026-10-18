<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\CourseTrack;
use App\Enums\EssentialsIcon;
use App\Models\Course;
use App\Services\Content\LessonService;
use Illuminate\Database\Seeder;

/**
 * The starter English Essentials topics the client listed (client request
 * 2026-10-18): Letters & Spelling, Numbers, Time & Appointments, Greetings
 * and Asking for Clarification, each with one beginner lesson holding the
 * default steps.
 *
 * Everything lands as a DRAFT for every department: the Super Admin writes
 * or generates the content in Lessons & Content (department list → English
 * Essentials), narrows the audience if needed and publishes. Nothing here
 * is shown to a learner until then.
 *
 * Idempotent: keyed on the course slug, so running it again adds nothing.
 * Not called by DatabaseSeeder; run it once per environment:
 *     php artisan db:seed --class=EnglishEssentialsSeeder --force
 */
class EnglishEssentialsSeeder extends Seeder
{
    /**
     * @var list<array{slug: string, title: string, description: string, icon: EssentialsIcon, tone: string, lesson: string}>
     */
    private const array TOPICS = [
        [
            'slug' => 'english-essentials-letters-spelling',
            'title' => 'Letters & Spelling',
            'description' => 'Say the alphabet and spell a guest’s name.',
            'icon' => EssentialsIcon::Letters,
            'tone' => 'brand',
            'lesson' => 'Spelling a guest’s name',
        ],
        [
            'slug' => 'english-essentials-numbers',
            'title' => 'Numbers',
            'description' => 'Room numbers, prices and phone numbers.',
            'icon' => EssentialsIcon::Numbers,
            'tone' => 'aqua',
            'lesson' => 'Room numbers and prices',
        ],
        [
            'slug' => 'english-essentials-time-appointments',
            'title' => 'Time & Appointments',
            'description' => 'Tell the time, dates and opening hours.',
            'icon' => EssentialsIcon::Time,
            'tone' => 'warning',
            'lesson' => 'Telling the time',
        ],
        [
            'slug' => 'english-essentials-greetings',
            'title' => 'Greetings',
            'description' => 'Welcome guests and say goodbye politely.',
            'icon' => EssentialsIcon::Greetings,
            'tone' => 'success',
            'lesson' => 'Greeting guests politely',
        ],
        [
            'slug' => 'english-essentials-clarification',
            'title' => 'Asking for Clarification',
            'description' => 'What to say when you do not understand the guest.',
            'icon' => EssentialsIcon::Clarification,
            'tone' => 'ai',
            'lesson' => 'When you don’t understand',
        ],
    ];

    public function run(LessonService $lessons): void
    {
        foreach (self::TOPICS as $position => $topic) {
            if (Course::query()->where('slug', $topic['slug'])->exists()) {
                continue;
            }

            $course = new Course;
            $course->forceFill([
                'track' => CourseTrack::Essentials,
                'department_id' => null,
                'hotel_id' => null,
                'level' => null,
                'title' => $topic['title'],
                'slug' => $topic['slug'],
                'description' => $topic['description'],
                'icon' => $topic['icon'],
                'tone' => $topic['tone'],
                'position' => $position + 1,
                'status' => ContentStatus::Draft,
            ])->save();

            $unit = $lessons->createUnit($course, 'Unit 1');
            $lessons->createLesson($unit, $topic['lesson'], true);
        }
    }
}
